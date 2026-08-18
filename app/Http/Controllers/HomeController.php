<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\DepartmentPreference;
use App\Services\OrganizationDirectory;
use App\Services\RequesterItTimeline;
use App\Support\GanttTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HomeController extends Controller
{
    public function index(
        Request $request,
        OrganizationDirectory $organization,
        DepartmentPreference $departments,
        RequesterItTimeline $requesterTimeline,
    ): Response {
        $view = $request->string('view')->toString() === 'features' ? 'features' : 'projects';
        $profile = $request->user()
            ? $organization->profile($request->user())
            : $departments->from($request);
        $department = is_array($profile) ? $departments->normalize($profile) : null;
        $workspace = filled(config('organization.workspace_public_id'))
            ? Workspace::where('public_id', config('organization.workspace_public_id'))->first()
            : null;
        $timeline = $workspace
            ? $requesterTimeline->build(
                $workspace,
                $department && strcasecmp($department['code'], config('organization.it_department_code')) !== 0
                    ? $department['id']
                    : null,
                'monthly',
                $view,
            )
            : $this->emptyTimeline($view);
        $visibleItems = $department ? $timeline['timelineRows']->take(5) : collect();

        $response = response()->view('welcome', [
            'view' => $view,
            'department' => $department,
            'deliveryWorkspace' => $workspace,
            'timeline' => $timeline['timeline'],
            'timelineRows' => $visibleItems,
            'itemCount' => $timeline['itemCount'],
            'scheduledTaskCount' => $timeline['scheduledTaskCount'],
            'averageProgress' => (int) round($timeline['timelineRows']->avg('progress') ?? 0),
            'updatedAt' => $timeline['updatedAt'],
        ]);

        if ($request->user() && is_array($profile) && ($cookie = $departments->remember($profile))) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    public function forget(): RedirectResponse
    {
        return redirect()->route('home')->withoutCookie(DepartmentPreference::COOKIE);
    }

    /** @return array<string, mixed> */
    private function emptyTimeline(string $view): array
    {
        return [
            'view' => $view,
            'timeline' => new GanttTimeline('monthly', config('app.timezone')),
            'timelineRows' => collect(),
            'itemCount' => 0,
            'scheduledTaskCount' => 0,
            'updatedAt' => now(),
        ];
    }
}
