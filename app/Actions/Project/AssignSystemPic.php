<?php

namespace App\Actions\Project;

use App\Enums\ProjectMemberRole;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A system serves several departments, and each names its own PIC: Avicenna answers to one
 * person for PPIC and another for Produksi, while staying one system with one board.
 */
class AssignSystemPic
{
    public function assign(Project $system, User $pic, int $departmentId, ?string $departmentCode, User $actor): ProjectMember
    {
        return DB::transaction(function () use ($system, $pic, $departmentId, $departmentCode, $actor): ProjectMember {
            // One PIC per department: naming a new one replaces the holder rather than leaving
            // two people who both think the other is handling it.
            $system->memberships()
                ->where('organization_department_id', $departmentId)
                ->where('user_id', '!=', $pic->id)
                ->where('role', ProjectMemberRole::MANAGER->value)
                ->update(['role' => ProjectMemberRole::MEMBER->value, 'organization_department_id' => null, 'organization_department_code' => null]);

            // Reuse the row this person already holds — their department-less manager row if
            // they created the system, or their existing one for this department. Creating a
            // second would list the same name twice on the same system.
            $assignment = $system->memberships()->where('user_id', $pic->id)
                ->where('organization_department_id', $departmentId)->first()
                ?? $system->memberships()->where('user_id', $pic->id)
                    ->whereNull('organization_department_id')->first();

            $attributes = [
                'role' => ProjectMemberRole::MANAGER->value,
                'organization_department_id' => $departmentId,
                'organization_department_code' => $departmentCode,
            ];

            if ($assignment) {
                $assignment->update($attributes);
            } else {
                $assignment = $system->memberships()->create(['user_id' => $pic->id, ...$attributes]);
            }

            ActivityLog::record($system->workspace, $system, 'system.pic_assigned', $actor, [
                'pic_public_id' => $pic->public_id,
                'organization_department_id' => $departmentId,
            ]);

            return $assignment;
        });
    }

    public function remove(Project $system, int $departmentId, User $actor): void
    {
        DB::transaction(function () use ($system, $departmentId, $actor): void {
            $assignment = $system->memberships()
                ->where('organization_department_id', $departmentId)
                ->where('role', ProjectMemberRole::MANAGER->value)
                ->first();

            if (! $assignment) {
                throw ValidationException::withMessages([
                    'organization_department_id' => 'That department has no PIC on this system.',
                ]);
            }

            // A system with no PIC at all cannot receive feature requests, so the last one is
            // kept. Removing it would take the system off the request form without saying so.
            if ($system->memberships()->where('role', ProjectMemberRole::MANAGER->value)->count() <= 1) {
                throw ValidationException::withMessages([
                    'organization_department_id' => 'Name another PIC first — a system with none cannot receive feature requests.',
                ]);
            }

            // Demoted rather than deleted: they keep their place on the system, they are simply
            // no longer the person that department is routed to.
            $assignment->update([
                'role' => ProjectMemberRole::MEMBER->value,
                'organization_department_id' => null,
                'organization_department_code' => null,
            ]);

            ActivityLog::record($system->workspace, $system, 'system.pic_removed', $actor, [
                'organization_department_id' => $departmentId,
            ]);
        });
    }
}
