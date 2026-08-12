<?php

namespace App\Services;

use App\Enums\OrganizationHierarchyLevel;
use App\Enums\OrganizationRankGroup;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrganizationDirectory
{
    /** @var array<string, Collection<int, User>> */
    private array $taskMemberCache = [];

    /** @var array<int, array<int, array<int, int>>> */
    private array $taskVisibilityCache = [];

    public function __construct(
        private readonly DepartmentWorkspaceService $departmentWorkspaces,
        private readonly OrphanedDataPruner $orphanedData,
    ) {}

    /** @return array<string, mixed>|null */
    public function profile(User $user): ?array
    {
        if (! config('organization.required')) {
            return null;
        }

        return $this->lookupProfile($user->organization_user_id, $user->email);
    }

    /** @return array<string, mixed>|null */
    public function authenticate(string $email, string $password): ?array
    {
        if (! config('organization.required') || ! config('organization.jit_auth')) {
            return null;
        }

        $profile = $this->lookupProfile(null, $email, true);
        $hash = $profile['credential_hash'] ?? null;

        try {
            if (! $hash || ! Hash::check($password, $hash)) {
                return null;
            }
        } catch (RuntimeException) {
            return null;
        }

        unset($profile['credential_hash']);

        return $profile;
    }

    public function credentialHash(User $user): ?string
    {
        if (! config('organization.required')) {
            return null;
        }

        try {
            $query = DB::connection(config('organization.connection'))->table('users');
            $user->organization_user_id
                ? $query->where('id', $user->organization_user_id)
                : $query->whereRaw('LOWER(email) = ?', [strtolower($user->email)]);

            return $query->value('password');
        } catch (QueryException) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    public function requireProfile(User $user): array
    {
        if ($profile = $this->profile($user)) {
            return $profile;
        }

        if (! config('organization.required')) {
            return [
                'organization_user_id' => null,
                'rank_code' => null,
                'rank_name' => null,
                'rank_group' => OrganizationRankGroup::MANAGEMENT,
                'division_id' => null,
                'division_code' => null,
                'division_name' => null,
                'department_id' => null,
                'department_code' => config('organization.it_department_code'),
                'department_name' => null,
                'section_id' => null,
                'section_name' => null,
            ];
        }

        throw ValidationException::withMessages([
            'organization' => 'Profil organisasi Anda belum lengkap atau memiliki lebih dari satu department. Hubungi administrator sebelum membuat permintaan.',
        ]);
    }

    /** @param array<string, mixed> $profile */
    public function requiresDepartmentApproval(array $profile): bool
    {
        return strcasecmp((string) $profile['department_code'], config('organization.it_department_code')) !== 0
            && $profile['rank_group'] !== OrganizationRankGroup::MANAGEMENT;
    }

    public function canApproveDepartment(User $user, int $departmentId): bool
    {
        $profile = $this->profile($user);

        return $profile !== null
            && $profile['rank_group'] === OrganizationRankGroup::MANAGEMENT
            && $profile['department_id'] === $departmentId
            && strcasecmp($profile['department_code'], config('organization.it_department_code')) !== 0;
    }

    /**
     * The viewer plus every lower-level workspace member in the same organisation branch.
     *
     * @return Collection<int, User>
     */
    public function taskMembers(User $viewer, Workspace $workspace): Collection
    {
        $key = $viewer->id.':'.$workspace->id;

        if (isset($this->taskMemberCache[$key])) {
            return $this->taskMemberCache[$key];
        }

        $members = $workspace->memberships()
            ->active()
            ->whereHas('user')
            ->with('user')
            ->orderBy('id')
            ->get()
            ->pluck('user')
            ->filter();

        if (! config('organization.required')) {
            return $this->taskMemberCache[$key] = $members->values();
        }

        $viewerProfile = $this->profile($viewer);
        $viewerLevel = OrganizationHierarchyLevel::fromCode($viewerProfile['rank_code'] ?? null);

        if (! $viewerProfile || ! $viewerLevel) {
            return $this->taskMemberCache[$key] = $members->where('id', $viewer->id)->values();
        }

        $profiles = $this->profilesFor($members);

        return $this->taskMemberCache[$key] = $members
            ->filter(function (User $member) use ($viewer, $viewerProfile, $viewerLevel, $profiles): bool {
                if ($member->is($viewer)) {
                    return true;
                }

                $profile = $profiles->get($member->id);
                $level = OrganizationHierarchyLevel::fromCode($profile['rank_code'] ?? null);

                return $profile !== null
                    && $level !== null
                    && $viewerLevel->isAbove($level)
                    && $this->sameTaskBranch($viewerProfile, $profile, $viewerLevel);
            })
            ->values();
    }

    /** @return array<int, array<int, int>> workspace id => visible Orbitra user ids */
    public function taskVisibility(User $viewer): array
    {
        if (isset($this->taskVisibilityCache[$viewer->id])) {
            return $this->taskVisibilityCache[$viewer->id];
        }

        $workspaces = $viewer->workspaceMemberships()->active()->with('workspace')->get()->pluck('workspace')->filter();

        return $this->taskVisibilityCache[$viewer->id] = $workspaces
            ->mapWithKeys(fn (Workspace $workspace) => [
                $workspace->id => $this->taskMembers($viewer, $workspace)->pluck('id')->all(),
            ])
            ->all();
    }

    public function canViewTasksOf(User $viewer, User $subject, Workspace $workspace): bool
    {
        return ! config('organization.required')
            || $this->taskMembers($viewer, $workspace)->contains(fn (User $member) => $member->is($subject));
    }

    public function syncMembershipRoles(User $user): bool
    {
        $profile = $this->profile($user);

        if (! $profile) {
            if ($user->isOrganizationManaged() && $this->isMissingFromDirectory($user)) {
                $this->orphanedData->purge($user);
            }

            return false;
        }

        $role = $this->workspaceRole($profile);
        $hasProtectedRole = $user->workspaceMemberships()->active()
            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
            ->exists();

        $identity = [
            'organization_user_id' => $profile['organization_user_id'],
            'organization_synced_at' => now(),
        ];

        if (config('organization.jit_auth') && ! $hasProtectedRole && ! $user->isOrganizationManaged()) {
            $identity['auth_source'] = 'organization';
            $identity['password'] = Str::random(64);
            $identity['remember_token'] = null;
        }

        $user->forceFill($identity)->save();

        if (config('organization.jit_auth') && ! $hasProtectedRole) {
            $this->departmentWorkspaces->syncMembership($user, $profile, $role);
        } else {
            $user->workspaceMemberships()
                ->active()
                ->whereNotIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
                ->where('role', '!=', $role->value)
                ->update(['role' => $role->value, 'updated_at' => now()]);
        }

        $user->unsetRelation('workspaceMemberships');

        return true;
    }

    /** @param array<string, mixed> $profile */
    public function workspaceRole(array $profile): WorkspaceRole
    {
        return strcasecmp($profile['department_code'], config('organization.it_department_code')) !== 0
            ? WorkspaceRole::REQUESTER
            : match ($profile['rank_group']) {
                OrganizationRankGroup::MANAGEMENT => WorkspaceRole::MANAGER,
                OrganizationRankGroup::SUPERVISION => WorkspaceRole::SUPERVISOR,
                OrganizationRankGroup::STAFF => WorkspaceRole::MEMBER,
            };
    }

    /** @return Collection<int, User> */
    public function departmentApprovers(Workspace $workspace, int $departmentId): Collection
    {
        if (config('organization.jit_auth')) {
            $workspace = $this->departmentWorkspace($departmentId) ?? $workspace;
        } elseif ($workspace->organization_department_id !== null
            && $workspace->organization_department_id !== $departmentId) {
            return collect();
        }

        try {
            $emails = DB::connection(config('organization.connection'))
                ->table('users as users')
                ->join('model_has_job_ranks as user_rank', 'user_rank.model_id', '=', 'users.id')
                ->join('job_ranks as rank', 'rank.id', '=', 'user_rank.job_rank_id')
                ->join('model_has_departments as user_department', 'user_department.model_id', '=', 'users.id')
                ->where('user_department.department_id', $departmentId)
                ->whereIn('rank.code', OrganizationRankGroup::managementCodes())
                ->whereNotNull('users.email')
                ->pluck('users.email')
                ->map(fn (string $email) => strtolower($email))
                ->unique()
                ->values();
        } catch (QueryException) {
            return collect();
        }

        if ($emails->isEmpty()) {
            return collect();
        }

        return $workspace->memberships()
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->whereHas('user', fn ($query) => $query->whereIn(DB::raw('LOWER(email)'), $emails))
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->values();
    }

    /**
     * Every department in the organisation directory, for pickers.
     *
     * Returns an empty collection when the directory is unreachable, the same as the other
     * readers here. The caller must treat "no departments" as "cannot ask right now", never
     * as "this organisation has none".
     *
     * @return Collection<int, object{id: int, code: string|null, name: string}>
     */
    public function departments(): Collection
    {
        try {
            return DB::connection(config('organization.connection'))
                ->table('departments')
                ->select('id', 'code', 'name')
                ->orderBy('name')
                ->get();
        } catch (QueryException) {
            return collect();
        }
    }

    public function departmentWorkspace(?int $departmentId): ?Workspace
    {
        if (! $departmentId) {
            return null;
        }

        return Workspace::where('organization_department_id', $departmentId)->first();
    }

    /** @param array<string, mixed> $profile */
    public function snapshot(array $profile): array
    {
        return collect($profile)->except(['rank_group', 'email', 'name', 'credential_hash'])->mapWithKeys(fn ($value, $key) => [
            match ($key) {
                'organization_user_id' => 'organization_user_id',
                'rank_code' => 'requester_job_rank_code',
                'rank_name' => 'requester_job_rank_name',
                'division_id' => 'requester_division_external_id',
                'division_code' => 'requester_division_code',
                'division_name' => 'requester_division_name',
                'department_id' => 'requester_department_external_id',
                'department_code' => 'requester_department_code',
                'department_name' => 'requester_department_name',
                'section_id' => 'requester_section_external_id',
                'section_name' => 'requester_section_name',
            } => $value,
        ])->all();
    }

    /** @return array<string, mixed>|null */
    private function lookupProfile(?int $organizationUserId, ?string $email, bool $withPassword = false): ?array
    {
        if (! $organizationUserId && blank($email)) {
            return null;
        }

        try {
            $query = DB::connection(config('organization.connection'))
                ->table('users as users')
                ->leftJoin('model_has_job_ranks as user_rank', 'user_rank.model_id', '=', 'users.id')
                ->leftJoin('job_ranks as rank', 'rank.id', '=', 'user_rank.job_rank_id')
                ->leftJoin('model_has_departments as user_department', 'user_department.model_id', '=', 'users.id')
                ->leftJoin('departments as department', 'department.id', '=', 'user_department.department_id')
                ->leftJoin('divisions as division', 'division.id', '=', 'department.division_id')
                ->leftJoin('model_has_sections as user_section', 'user_section.model_id', '=', 'users.id')
                ->leftJoin('sections as section', 'section.id', '=', 'user_section.section_id');

            $organizationUserId
                ? $query->where('users.id', $organizationUserId)
                : $query->whereRaw('LOWER(users.email) = ?', [strtolower((string) $email)]);

            $columns = [
                'users.id as organization_user_id',
                'users.name as name',
                'users.email as email',
                'rank.code as rank_code',
                'rank.name as rank_name',
                'division.id as division_id',
                'division.code as division_code',
                'division.name as division_name',
                'department.id as department_id',
                'department.code as department_code',
                'department.name as department_name',
                'section.id as section_id',
                'section.name as section_name',
            ];

            if ($withPassword) {
                $columns[] = 'users.password as credential_hash';
            }

            $rows = $query->select($columns)->get();
        } catch (QueryException) {
            return null;
        }

        return $this->profileFromRows($rows, $withPassword);
    }

    /** @param Collection<int, User> $users
     * @return Collection<int, array<string, mixed>> keyed by Orbitra user id
     */
    private function profilesFor(Collection $users): Collection
    {
        $organizationIds = $users->pluck('organization_user_id')->filter()->map(fn ($id) => (int) $id)->values();
        $emails = $users->pluck('email')->filter()->map(fn (string $email) => strtolower($email))->values();

        try {
            $rows = DB::connection(config('organization.connection'))
                ->table('users as users')
                ->leftJoin('model_has_job_ranks as user_rank', 'user_rank.model_id', '=', 'users.id')
                ->leftJoin('job_ranks as rank', 'rank.id', '=', 'user_rank.job_rank_id')
                ->leftJoin('model_has_departments as user_department', 'user_department.model_id', '=', 'users.id')
                ->leftJoin('departments as department', 'department.id', '=', 'user_department.department_id')
                ->leftJoin('divisions as division', 'division.id', '=', 'department.division_id')
                ->leftJoin('model_has_sections as user_section', 'user_section.model_id', '=', 'users.id')
                ->leftJoin('sections as section', 'section.id', '=', 'user_section.section_id')
                ->where(function ($query) use ($organizationIds, $emails): void {
                    $query->whereIn('users.id', $organizationIds)
                        ->orWhereIn(DB::raw('LOWER(users.email)'), $emails);
                })
                ->select([
                    'users.id as organization_user_id',
                    'users.name as name',
                    'users.email as email',
                    'rank.code as rank_code',
                    'rank.name as rank_name',
                    'division.id as division_id',
                    'division.code as division_code',
                    'division.name as division_name',
                    'department.id as department_id',
                    'department.code as department_code',
                    'department.name as department_name',
                    'section.id as section_id',
                    'section.name as section_name',
                ])
                ->get();
        } catch (QueryException) {
            return collect();
        }

        $byId = $rows->groupBy('organization_user_id');
        $byEmail = $rows->groupBy(fn ($row) => strtolower((string) $row->email));

        return $users->mapWithKeys(function (User $user) use ($byId, $byEmail): array {
            $rows = $user->organization_user_id
                ? $byId->get($user->organization_user_id, collect())
                : $byEmail->get(strtolower($user->email), collect());

            $profile = $this->profileFromRows($rows);

            return $profile ? [$user->id => $profile] : [];
        });
    }

    /** @return array<string, mixed>|null */
    private function profileFromRows(Collection $rows, bool $withPassword = false): ?array
    {
        if ($rows->isEmpty() || $rows->pluck('department_id')->filter()->unique()->count() !== 1) {
            return null;
        }

        $row = $rows->first();
        $group = OrganizationRankGroup::fromCode($row->rank_code);

        if (! $group || ! $row->department_id) {
            return null;
        }

        return [
            'organization_user_id' => (int) $row->organization_user_id,
            'name' => $row->name,
            'email' => strtolower($row->email),
            'rank_code' => strtoupper($row->rank_code),
            'rank_name' => $row->rank_name,
            'rank_group' => $group,
            'division_id' => (int) $row->division_id,
            'division_code' => $row->division_code,
            'division_name' => $row->division_name,
            'department_id' => (int) $row->department_id,
            'department_code' => $row->department_code,
            'department_name' => $row->department_name,
            'section_id' => $row->section_id ? (int) $row->section_id : null,
            'section_name' => $row->section_name,
            ...($withPassword ? ['credential_hash' => $row->credential_hash] : []),
        ];
    }

    /** @param array<string, mixed> $viewer
     * @param  array<string, mixed>  $member
     */
    private function sameTaskBranch(array $viewer, array $member, OrganizationHierarchyLevel $level): bool
    {
        return match ($level) {
            OrganizationHierarchyLevel::GROUP_MANAGER => $viewer['division_id'] !== null
                && $viewer['division_id'] === $member['division_id'],
            OrganizationHierarchyLevel::MANAGER => $viewer['department_id'] === $member['department_id'],
            OrganizationHierarchyLevel::SUPERVISOR, OrganizationHierarchyLevel::STAFF => $viewer['section_id'] !== null
                ? $viewer['section_id'] === $member['section_id']
                : $viewer['department_id'] === $member['department_id'],
            OrganizationHierarchyLevel::OPERATOR => false,
        };
    }

    private function isMissingFromDirectory(User $user): bool
    {
        try {
            $query = DB::connection(config('organization.connection'))->table('users');
            $user->organization_user_id
                ? $query->where('id', $user->organization_user_id)
                : $query->whereRaw('LOWER(email) = ?', [strtolower($user->email)]);

            return ! $query->exists();
        } catch (QueryException) {
            return false;
        }
    }
}
