<?php

namespace App\Actions\User;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Removes a person without removing the work they touched.
 *
 * Every record they authored — requests, approvals, attachments, messages, tasks — is handed
 * to a placeholder account first, then the user row is deleted. Their own belongings
 * (memberships, assignments, capacity, preferences) go with them through existing cascades.
 *
 * A plain CASCADE on these columns would have deleted the requests other people approved and
 * the attachments they approved against. Removing a leaver is not a reason to lose the
 * decisions the company made.
 */
class DeleteUserAccount
{
    public const PLACEHOLDER_EMAIL = 'deleted-user@invalid.local';

    /**
     * Columns that must survive the person, keyed by table. Each is reassigned rather than
     * cascaded.
     *
     * Columns declared nullOnDelete are deliberately absent: the schema already decided they
     * should empty out, and the screens that read them already say "the team" when they do.
     *
     * @var array<string, string>
     */
    private const AUTHORED = [
        'workspace_invitations' => 'invited_by',
        'tasks' => 'creator_id',
        'task_assignees' => 'assigned_by',
        'task_comments' => 'author_id',
        'files' => 'uploader_id',
        'schedule_events' => 'creator_id',
        'conversations' => 'created_by',
        'messages' => 'sender_id',
        'support_tickets' => 'requester_id',
        'feature_requests' => 'requester_id',
        'project_requests' => 'requester_id',
        'validation_checkpoints' => 'requester_id',
    ];

    public function handle(User $user, ?User $actor = null): void
    {
        if ($actor && $actor->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'Deleting your own account from here would sign you out mid-request. Ask another administrator.',
            ]);
        }

        // Ownership is checked before reach, and the order is deliberate: an owner also fails
        // the reach test, and "you do not administer them" would send the reader looking for a
        // permission problem when the real answer is one transfer away.
        //
        // It is also the one thing that cannot be handed to a placeholder: a workspace whose
        // owner is nobody has no one who can grant access to it again.
        $owned = Workspace::where('owner_id', $user->id)->pluck('name');

        if ($owned->isNotEmpty()) {
            throw ValidationException::withMessages([
                'user' => 'Transfer ownership of '.$owned->join(', ', ' and ').' before deleting this account.',
            ]);
        }

        // Deleting an account reaches every workspace the person belongs to, so the authority
        // to do it has to reach that far as well. An admin of one workspace must not be able
        // to erase someone who also works in another they have no say over.
        if ($actor) {
            $beyondReach = $user->workspaceMemberships()->with('workspace')->get()
                ->reject(fn ($membership) => $actor->can('deleteAccount', $membership));

            if ($beyondReach->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'user' => 'This person also belongs to '.$beyondReach->pluck('workspace.name')->join(', ', ' and ')
                        .', which you do not administer. Deleting the account has to come from someone who does.',
                ]);
            }
        }

        DB::transaction(function () use ($user, $actor): void {
            $placeholder = $this->placeholder();

            // Captured before the memberships cascade away, so each workspace this person
            // belonged to still gets the record in its own timeline. activity_logs.workspace_id
            // is not nullable, so there is no such thing as a workspace-less entry.
            $workspaceIds = $user->workspaceMemberships()->pluck('workspace_id');

            foreach (self::AUTHORED as $table => $column) {
                DB::table($table)->where($column, $user->id)->update([$column => $placeholder->id]);
            }

            // Polymorphic tables carry no foreign key, so no cascade reaches them.
            DB::table('push_subscriptions')
                ->where('subscribable_type', $user->getMorphClass())
                ->where('subscribable_id', $user->id)
                ->delete();

            DB::table('notifications')
                ->where('notifiable_type', $user->getMorphClass())
                ->where('notifiable_id', $user->id)
                ->delete();

            $name = $user->name;
            $user->delete();

            foreach ($workspaceIds as $workspaceId) {
                ActivityLog::create([
                    'workspace_id' => $workspaceId,
                    'actor_id' => $actor?->id,
                    'subject_type' => $placeholder->getMorphClass(),
                    'subject_id' => $placeholder->id,
                    'action' => 'user.deleted',
                    'metadata_json' => ['name' => $name],
                    'created_at' => now(),
                ]);
            }
        });
    }

    /**
     * One shared account that owns nothing and belongs to no workspace, so it never appears in
     * a member list, a PIC picker, or an assignee picker — all of which are driven by
     * membership rather than by the users table.
     */
    private function placeholder(): User
    {
        return User::firstOrCreate(
            ['email' => self::PLACEHOLDER_EMAIL],
            [
                'first_name' => 'Deleted',
                'last_name' => 'user',
                // Hashed by the model cast, and never given to anyone: the account exists to
                // hold references, not to be signed in to.
                'password' => Str::random(64),
                'email_verified_at' => null,
                'timezone' => 'UTC',
                'locale' => 'en',
                'theme' => 'dark',
            ],
        );
    }
}
