<?php

namespace App\Http\Requests\Master;

use App\Enums\WorkspaceMemberStatus;
use App\Models\Workspace;
use Illuminate\Validation\Rule;

class StoreSystemRequest extends MasterDataRequest
{
    public function rules(): array
    {
        $workspace = $this->targetWorkspace();

        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('projects', 'name')
                    ->where('workspace_id', $workspace?->id)
                    ->ignore($this->route('system')?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // The PIC must be someone who can actually work on it: an active member who is
            // not a requester.
            'pic_public_id' => [
                'required', 'string',
                Rule::exists('users', 'public_id')->where(
                    fn ($query) => $query->whereIn('id', $this->eligiblePicIds($workspace))
                ),
            ],
        ];
    }

    /** @return array<int> */
    private function eligiblePicIds(?Workspace $workspace): array
    {
        return $workspace
            ? $workspace->memberships()
                ->where('status', WorkspaceMemberStatus::ACTIVE->value)
                ->get()
                ->filter(fn ($membership) => $membership->role->canContribute())
                ->pluck('user_id')
                ->all()
            : [];
    }
}
