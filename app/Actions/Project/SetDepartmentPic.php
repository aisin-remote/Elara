<?php

namespace App\Actions\Project;

use App\Models\ActivityLog;
use App\Models\DepartmentPic;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class SetDepartmentPic
{
    public function __construct(private readonly AssignSystemPic $assignSystemPic) {}

    /** Set the default once and keep every existing system for this department in sync. */
    public function handle(Workspace $workspace, object $department, User $pic, User $actor): DepartmentPic
    {
        return DB::transaction(function () use ($workspace, $department, $pic, $actor): DepartmentPic {
            $mapping = DepartmentPic::updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'organization_department_id' => (int) $department->id,
                ],
                [
                    'organization_department_code' => $department->code,
                    'pic_id' => $pic->id,
                ],
            );

            $systems = $workspace->projects()
                ->systems()
                ->whereHas('memberships', fn ($memberships) => $memberships
                    ->where('organization_department_id', $department->id))
                ->get();

            foreach ($systems as $system) {
                $this->assignSystemPic->assign(
                    $system,
                    $pic,
                    (int) $department->id,
                    $department->code,
                    $actor,
                );
            }

            ActivityLog::record($workspace, $mapping, 'department.pic_updated', $actor, [
                'organization_department_id' => (int) $department->id,
                'pic_public_id' => $pic->public_id,
                'systems_synced' => $systems->count(),
            ]);

            return $mapping;
        });
    }
}
