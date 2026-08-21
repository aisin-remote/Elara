<?php

use App\Http\Controllers\App\ApprovalController;
use App\Http\Controllers\App\AskAiController;
use App\Http\Controllers\App\DashboardController;
use App\Http\Controllers\App\FeatureController;
use App\Http\Controllers\App\HelpController;
use App\Http\Controllers\App\IntegrationController;
use App\Http\Controllers\App\InvitationController;
use App\Http\Controllers\App\MasterDataController;
use App\Http\Controllers\App\MeetingMinuteController;
use App\Http\Controllers\App\MessagesController;
use App\Http\Controllers\App\NotificationSettingsController;
use App\Http\Controllers\App\PerformanceController;
use App\Http\Controllers\App\PortfolioController;
use App\Http\Controllers\App\ProjectApprovalController;
use App\Http\Controllers\App\ProjectController;
use App\Http\Controllers\App\ProjectFileController;
use App\Http\Controllers\App\ScheduleController;
use App\Http\Controllers\App\SearchController;
use App\Http\Controllers\App\SettingsController;
use App\Http\Controllers\App\SupportingTaskController;
use App\Http\Controllers\App\TaskController;
use App\Http\Controllers\App\TeamController;
use App\Http\Controllers\App\WorkspaceController;
use App\Http\Controllers\Desk\DepartmentApprovalController;
use App\Http\Controllers\Desk\FeatureRequestController as DeskFeatureRequestController;
use App\Http\Controllers\Desk\ItTimelineController;
use App\Http\Controllers\Desk\MeetingMinuteController as DeskMeetingMinuteController;
use App\Http\Controllers\Desk\ProjectRequestController as DeskProjectRequestController;
use App\Http\Controllers\Desk\RequesterDeskController;
use App\Http\Controllers\Desk\ScheduleController as DeskScheduleController;
use App\Http\Controllers\Desk\SupportingRequestController;
use App\Http\Controllers\Desk\ValidationController as DeskValidationController;
use App\Http\Controllers\HomeController;
use App\Http\Middleware\EnsureDeliveryDeskAccess;
use App\Http\Middleware\EnsureRequestDeskAccess;
use App\Http\Middleware\RequireEmailVerificationWhenEnabled;
use App\Http\Middleware\ShareWorkspaceNavigation;
use App\Http\Middleware\SyncOrganizationRole;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/forget-department', [HomeController::class, 'forget'])->name('home.forget-department');
Route::view('/privacy', 'legal', ['document' => 'privacy'])->name('legal.privacy');
Route::view('/terms', 'legal', ['document' => 'terms'])->name('legal.terms');
Route::view('/accessibility', 'legal', ['document' => 'accessibility'])->name('legal.accessibility');

// Requester desk. Separate group, separate layout, no delivery views.
Route::middleware(['auth', RequireEmailVerificationWhenEnabled::class, SyncOrganizationRole::class, EnsureRequestDeskAccess::class, ShareWorkspaceNavigation::class])->group(function (): void {
    Route::get('/desk', [RequesterDeskController::class, 'index'])->name('desk.index');
    Route::get('/desk/it-timeline', [ItTimelineController::class, 'index'])->name('desk.it-timeline');
    Route::get('/desk/workspaces/{workspace}/schedule', [DeskScheduleController::class, 'index'])->name('desk.schedule.index');
    Route::get('/desk/workspaces/{workspace}/schedule/events', [DeskScheduleController::class, 'events'])->name('desk.schedule.events');
    Route::post('/desk/workspaces/{workspace}/schedule', [DeskScheduleController::class, 'store'])->name('desk.schedule.store');
    Route::get('/desk/workspaces/{workspace}/schedule/events/{event}/mom/create', [DeskMeetingMinuteController::class, 'create'])->name('desk.schedule.mom.create');
    Route::post('/desk/workspaces/{workspace}/schedule/events/{event}/mom', [DeskMeetingMinuteController::class, 'store'])->name('desk.schedule.mom.store');
    Route::post('/desk/workspaces/{workspace}/schedule/events/{event}/mom/summary', [DeskMeetingMinuteController::class, 'summary'])->middleware('throttle:10,1')->name('desk.schedule.mom.summary');
    Route::get('/desk/workspaces/{workspace}/schedule/mom/{meetingMinute}', [DeskMeetingMinuteController::class, 'show'])->name('desk.schedule.mom.show');
    Route::get('/desk/workspaces/{workspace}/schedule/mom/{meetingMinute}/files/{file}', [DeskMeetingMinuteController::class, 'download'])->name('desk.schedule.mom.files.download');
    Route::get('/desk/workspaces/{workspace}/approvals', [DepartmentApprovalController::class, 'index'])->name('desk.department-approvals.index');
    Route::post('/desk/workspaces/{workspace}/approvals/features/{featureRequest}', [DepartmentApprovalController::class, 'decideFeature'])->name('desk.department-approvals.features.decide');
    Route::post('/desk/workspaces/{workspace}/approvals/projects/{projectRequest}', [DepartmentApprovalController::class, 'decideProject'])->name('desk.department-approvals.projects.decide');
    Route::get('/desk/workspaces/{workspace}/requests/new', [DeskFeatureRequestController::class, 'create'])->name('desk.requests.create');
    Route::post('/desk/workspaces/{workspace}/requests', [DeskFeatureRequestController::class, 'store'])->name('desk.requests.store');
    Route::get('/desk/requests/{featureRequest}', [DeskFeatureRequestController::class, 'show'])->name('desk.requests.show');
    Route::post('/desk/requests/{featureRequest}/resubmit', [DeskFeatureRequestController::class, 'resubmit'])->name('desk.requests.resubmit');
    Route::post('/desk/requests/{featureRequest}/withdraw', [DeskFeatureRequestController::class, 'withdraw'])->name('desk.requests.withdraw');
    Route::get('/desk/workspaces/{workspace}/project-requests/new', [DeskProjectRequestController::class, 'create'])->name('desk.project-requests.create');
    Route::post('/desk/workspaces/{workspace}/project-requests', [DeskProjectRequestController::class, 'store'])->name('desk.project-requests.store');
    Route::get('/desk/project-requests/{projectRequest}', [DeskProjectRequestController::class, 'show'])->name('desk.project-requests.show');
    Route::post('/desk/project-requests/{projectRequest}/resubmit', [DeskProjectRequestController::class, 'resubmit'])->name('desk.project-requests.resubmit');
    Route::post('/desk/project-requests/{projectRequest}/withdraw', [DeskProjectRequestController::class, 'withdraw'])->name('desk.project-requests.withdraw');
    Route::get('/desk/workspaces/{workspace}/supporting/new', [SupportingRequestController::class, 'create'])->name('desk.supporting.create');
    Route::post('/desk/workspaces/{workspace}/supporting', [SupportingRequestController::class, 'store'])->name('desk.supporting.store');
    Route::get('/desk/supporting/{supportingTask}', [SupportingRequestController::class, 'show'])->name('desk.supporting.show');
    Route::get('/desk/validations', [DeskValidationController::class, 'index'])->name('desk.validations.index');
    Route::post('/desk/validations/{checkpoint}', [DeskValidationController::class, 'respond'])->name('desk.validations.respond');
});

Route::middleware(['auth', RequireEmailVerificationWhenEnabled::class, SyncOrganizationRole::class, EnsureDeliveryDeskAccess::class, ShareWorkspaceNavigation::class])->group(function (): void {
    Route::get('/app', [DashboardController::class, 'index'])->name('app.dashboard');
    Route::get('/app/workspaces/create', [WorkspaceController::class, 'create'])->name('app.workspaces.create');
    Route::get('/app/workspaces/{workspace}', [DashboardController::class, 'show'])->name('app.workspaces.show');
    Route::get('/app/workspaces/{workspace}/settings', [WorkspaceController::class, 'settings'])->name('app.workspaces.settings');
    Route::get('/app/workspaces/{workspace}/settings/master', [MasterDataController::class, 'index'])->name('app.settings.master');
    Route::get('/app/workspaces/{workspace}/settings/master/categories', [MasterDataController::class, 'categories'])->name('app.settings.master.categories');
    Route::get('/app/workspaces/{workspace}/settings/master/status-templates', [MasterDataController::class, 'statusTemplates'])->name('app.settings.master.status-templates');
    Route::get('/app/workspaces/{workspace}/settings/master/capacity', [MasterDataController::class, 'capacity'])->name('app.settings.master.capacity');
    Route::get('/app/workspaces/{workspace}/settings/master/holidays', [MasterDataController::class, 'holidays'])->name('app.settings.master.holidays');
    Route::get('/app/workspaces/{workspace}/settings/master/rules', [MasterDataController::class, 'rules'])->name('app.settings.master.rules');
    Route::get('/app/workspaces/{workspace}/settings/master/articles', [MasterDataController::class, 'articles'])->name('app.settings.master.articles');
    Route::get('/app/workspaces/{workspace}/settings/profile', [SettingsController::class, 'profile'])->name('app.settings.profile');
    Route::get('/app/workspaces/{workspace}/settings/security', [SettingsController::class, 'security'])->name('app.settings.security');
    Route::get('/app/workspaces/{workspace}/settings/integrations', [IntegrationController::class, 'index'])->name('app.settings.integrations');
    Route::get('/app/workspaces/{workspace}/settings/notifications', [NotificationSettingsController::class, 'edit'])->name('app.settings.notifications');
    Route::get('/settings/security', [SettingsController::class, 'securityDefault'])->name('settings.security');
    Route::get('/settings/notifications', [NotificationSettingsController::class, 'default'])->name('settings.notifications');
    Route::get('/settings/integrations', [IntegrationController::class, 'default'])->name('settings.integrations');
    Route::get('/app/workspaces/{workspace}/team', [TeamController::class, 'index'])->name('app.workspaces.team');
    Route::get('/app/workspaces/{workspace}/team/{member}', [TeamController::class, 'show'])->name('app.workspaces.team.show');
    Route::get('/app/workspaces/{workspace}/messages', [MessagesController::class, 'index'])->name('app.messages.index');
    Route::get('/app/workspaces/{workspace}/ask-ai', [AskAiController::class, 'index'])->name('app.ai.index');
    Route::get('/app/workspaces/{workspace}/ask-ai/{aiConversation}', [AskAiController::class, 'show'])->name('app.ai.show');
    Route::get('/app/workspaces/{workspace}/approvals', [ApprovalController::class, 'index'])->name('app.approvals.index');
    Route::get('/app/workspaces/{workspace}/approvals/{featureRequest}', [ApprovalController::class, 'show'])->name('app.approvals.show');
    Route::post('/app/workspaces/{workspace}/approvals/{featureRequest}/decide', [ApprovalController::class, 'decide'])->name('app.approvals.decide');
    Route::get('/app/workspaces/{workspace}/approvals/projects/{projectRequest}', [ProjectApprovalController::class, 'show'])->name('app.approvals.projects.show');
    Route::post('/app/workspaces/{workspace}/approvals/projects/{projectRequest}/meeting', [ProjectApprovalController::class, 'scheduleMeeting'])->name('app.approvals.projects.meeting');
    Route::post('/app/workspaces/{workspace}/approvals/projects/{projectRequest}/meeting-held', [ProjectApprovalController::class, 'markMeetingHeld'])->name('app.approvals.projects.meeting-held');
    Route::post('/app/workspaces/{workspace}/approvals/projects/{projectRequest}/decide', [ProjectApprovalController::class, 'decide'])->name('app.approvals.projects.decide');
    Route::get('/app/workspaces/{workspace}/features', [FeatureController::class, 'index'])->name('app.features.index');
    Route::get('/app/workspaces/{workspace}/features/create', [FeatureController::class, 'create'])->name('app.features.create');
    Route::get('/app/workspaces/{workspace}/features/{system}', [FeatureController::class, 'show'])->name('app.features.show');
    Route::get('/app/workspaces/{workspace}/features/{system}/{feature}', [FeatureController::class, 'detail'])->name('app.features.detail');
    Route::get('/app/workspaces/{workspace}/settings/master/systems', [MasterDataController::class, 'systems'])->name('app.settings.master.systems');
    Route::get('/app/workspaces/{workspace}/projects', [ProjectController::class, 'index'])->name('app.projects.index');
    Route::get('/app/workspaces/{workspace}/projects/create', [ProjectController::class, 'create'])->name('app.projects.create');
    Route::get('/app/workspaces/{workspace}/projects/{project}/tasks', [TaskController::class, 'project'])->name('app.projects.tasks');
    Route::get('/app/workspaces/{workspace}/projects/{project}/board', [TaskController::class, 'board'])->name('app.projects.board');
    Route::get('/app/workspaces/{workspace}/projects/{project}/timeline', [ProjectController::class, 'timeline'])->name('app.projects.timeline');
    Route::get('/app/workspaces/{workspace}/projects/{project}/files', [ProjectFileController::class, 'index'])->name('app.projects.files');
    Route::get('/app/workspaces/{workspace}/schedule', [ScheduleController::class, 'index'])->name('app.schedule.index');
    Route::get('/app/workspaces/{workspace}/schedule/minutes', [MeetingMinuteController::class, 'index'])->name('app.schedule.minutes.index');
    Route::get('/app/workspaces/{workspace}/schedule/minutes/create', [MeetingMinuteController::class, 'create'])->name('app.schedule.minutes.create');
    Route::get('/app/workspaces/{workspace}/schedule/minutes/{meetingMinute}', [MeetingMinuteController::class, 'show'])->name('app.schedule.minutes.show');
    Route::get('/app/workspaces/{workspace}/schedule/minutes/{meetingMinute}/edit', [MeetingMinuteController::class, 'edit'])->name('app.schedule.minutes.edit');
    Route::get('/app/workspaces/{workspace}/performance', [PerformanceController::class, 'index'])->name('app.performance.index');
    Route::get('/app/workspaces/{workspace}/portfolio', [PortfolioController::class, 'index'])->name('app.portfolio.index');
    Route::get('/app/workspaces/{workspace}/tasks', [TaskController::class, 'global'])->name('app.tasks.index');
    Route::get('/app/workspaces/{workspace}/supporting', [SupportingTaskController::class, 'index'])->name('app.supporting.index');
    Route::get('/app/workspaces/{workspace}/supporting/create', [SupportingTaskController::class, 'create'])->name('app.supporting.create');
    Route::get('/app/workspaces/{workspace}/supporting/{supportingTask}/edit', [SupportingTaskController::class, 'edit'])->name('app.supporting.edit');
    Route::get('/app/workspaces/{workspace}/search', [SearchController::class, 'index'])->middleware('throttle:30,1')->name('app.search');
    Route::get('/app/projects/{project}', [ProjectController::class, 'show'])->name('app.projects.show');
    Route::get('/app/projects/{project}/edit', [ProjectController::class, 'edit'])->name('app.projects.edit');
    Route::get('/app/tasks/{task}', [TaskController::class, 'show'])->name('app.tasks.show');
    Route::get('/app/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::get('/help', [HelpController::class, 'index'])->middleware('throttle:30,1')->name('help');
    Route::get('/help/articles/{article:slug}', [HelpController::class, 'show'])->name('help.articles.show');
});

require __DIR__.'/auth.php';
