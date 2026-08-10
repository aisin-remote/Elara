<?php

namespace Database\Seeders;

use App\Actions\Project\CreateSystem;
use App\Actions\Request\ScheduleApprovedRequests;
use App\Actions\Request\TransitionProjectRequest;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectRequestStatus;
use App\Enums\ProjectType;
use App\Enums\RequestUrgency;
use App\Models\FeatureRequest;
use App\Models\MemberCapacity;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceHoliday;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;

/**
 * Fixtures for the request-to-delivery layer (PRD-01 … PRD-05), so every role has something
 * waiting for it at login. Run on its own with:
 *
 *   php artisan db:seed --class=RequestDeskSeeder
 *
 * Bare class name on purpose: db:seed prepends this namespace itself, and a fully qualified
 * name loses its backslashes in PowerShell.
 */
class RequestDeskSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development'])) {
            throw new \RuntimeException('RequestDeskSeeder is restricted to local and development environments.');
        }

        // Seeding drives the real Actions, which notify. Nobody wants a seeder emailing
        // seven addresses, or hanging on an SMTP connection, to produce fixtures.
        Notification::fake();

        $workspace = Workspace::where('name', 'Product Studio')->first();

        if (! $workspace) {
            $this->command?->warn('Product Studio workspace not found. Run DemoSeeder first.');

            return;
        }

        $users = User::whereIn('email', [
            'owner@example.com', 'manager@example.com', 'member@example.com', 'viewer@example.com',
            'supervisor@example.com', 'lead@example.com', 'requester@example.com',
        ])->get()->keyBy('email');

        $requester = $users['requester@example.com'];
        $supervisor = $users['supervisor@example.com'];
        $manager = $users['lead@example.com'];

        $systems = $this->systems($workspace, $users);
        $this->capacity($workspace, $users);
        $this->holidays($workspace);
        $this->featureRequests($workspace, $systems, $requester, $supervisor);
        $this->projectRequests($workspace, $requester, $supervisor, $manager);

        // Real planner, real dates: the seeded queue is drained the same way the hourly
        // command drains it, so the scheduled rows are not hand-written fiction.
        $scheduled = app(ScheduleApprovedRequests::class)->handle($workspace);

        $this->command?->info("Scheduled {$scheduled} approved request(s) through CapacityPlanner.");
        $this->table();
    }

    /** @return array<string, Project> */
    private function systems(Workspace $workspace, $users): array
    {
        $systems = [];

        foreach ([
            ['Inventory Core', 'Stock levels, receiving, and stock-take for every warehouse.', '#8b5cf6', 'lead@example.com'],
            ['Payroll Portal', 'Monthly payroll runs, payslips, and tax reporting.', '#2eb0fb', 'member@example.com'],
            ['Vendor Gateway', 'Purchase orders and supplier invoices.', '#10b981', 'supervisor@example.com'],
        ] as [$name, $description, $color, $picEmail]) {
            $existing = Project::where('workspace_id', $workspace->id)
                ->where('name', $name)
                ->where('type', ProjectType::SYSTEM->value)
                ->first();

            $systems[$name] = $existing ?? app(CreateSystem::class)->handle($workspace, $users['owner@example.com'], [
                'name' => $name,
                'description' => $description,
                'color' => $color,
                'pic_id' => $users[$picEmail]->id,
            ]);
        }

        return $systems;
    }

    private function capacity(Workspace $workspace, $users): void
    {
        // Deliberately uneven, so "who is free first" has a visible answer during a trial.
        foreach ([
            'lead@example.com' => [6.0, [1, 2, 3, 4, 5]],
            'member@example.com' => [7.0, [1, 2, 3, 4, 5]],
            'supervisor@example.com' => [4.0, [1, 2, 3, 4, 5]],
            'manager@example.com' => [5.0, [1, 2, 3, 4]],
        ] as $email => [$hours, $days]) {
            MemberCapacity::updateOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $users[$email]->id, 'effective_from' => now()->startOfMonth()->toDateString()],
                ['hours_per_day' => $hours, 'working_days' => $days],
            );
        }
    }

    private function holidays(Workspace $workspace): void
    {
        foreach ([
            [now()->addDays(9)->toDateString(), 'Company offsite'],
            [now()->addDays(23)->toDateString(), 'Public holiday'],
        ] as [$date, $name]) {
            WorkspaceHoliday::updateOrCreate(
                ['workspace_id' => $workspace->id, 'observed_on' => $date],
                ['name' => $name],
            );
        }
    }

    /** @param  array<string, Project>  $systems */
    private function featureRequests(Workspace $workspace, array $systems, User $requester, User $supervisor): void
    {
        foreach ([
            [
                'Inventory Core', 'Export the monthly stock report',
                'We copy stock numbers into a spreadsheet by hand every month and it takes two full days.',
                'A download button that produces the same columns the finance team already uses.',
                RequestUrgency::NORMAL, FeatureRequestStatus::PENDING_REVIEW, null, null,
            ],
            [
                'Payroll Portal', 'Show last year figures beside this month',
                'Every payroll review means opening last year in a second tab and comparing by eye.',
                'The previous year column shown next to the current one on the same screen.',
                RequestUrgency::LOW, FeatureRequestStatus::PENDING_REVIEW, null, null,
            ],
            [
                'Vendor Gateway', 'Warn before a duplicate purchase order',
                'Two people can raise the same order and nothing stops them until the invoice arrives.',
                'A warning when an order matches an open one for the same supplier and amount.',
                RequestUrgency::HIGH, FeatureRequestStatus::NEEDS_INFO, 'Which fields should count as a duplicate? Amount alone is too loose.', null,
            ],
            [
                'Inventory Core', 'Bulk edit reorder levels',
                'Reorder levels are set one product at a time, and there are eleven hundred products.',
                'Select many products and set the reorder level once.',
                RequestUrgency::NORMAL, FeatureRequestStatus::APPROVED, 'Clear enough to start.', 12 * 60,
            ],
            [
                'Payroll Portal', 'Email payslips automatically',
                'Payslips are attached to individual emails by hand on the 25th of every month.',
                'Payslips sent automatically once the run is approved.',
                RequestUrgency::HIGH, FeatureRequestStatus::APPROVED, 'Approved, sensitive data so it needs care.', 20 * 60,
            ],
            [
                'Vendor Gateway', 'Rebuild the supplier list in a new colour scheme',
                'The supplier list does not match the new brand colours.',
                'The same list, restyled.',
                RequestUrgency::LOW, FeatureRequestStatus::REJECTED, 'Cosmetic only, and it would block two weeks of delivery work. Revisit with the brand refresh.', null,
            ],
        ] as [$systemName, $title, $problem, $outcome, $urgency, $status, $note, $minutes]) {
            $decided = in_array($status, [FeatureRequestStatus::APPROVED, FeatureRequestStatus::REJECTED, FeatureRequestStatus::NEEDS_INFO], true);

            // firstOrCreate, never update: re-seeding must not rewind a decision a human made.
            // updateOrCreate here once reset approved requests back to pending_review and took
            // their review panel away with them.
            FeatureRequest::firstOrCreate(
                ['workspace_id' => $workspace->id, 'title' => $title],
                [
                    'project_id' => $systems[$systemName]->id,
                    'requester_id' => $requester->id,
                    'problem' => $problem,
                    'desired_outcome' => $outcome,
                    'urgency' => $urgency,
                    'status' => $status,
                    'reviewed_by' => $decided ? $supervisor->id : null,
                    'reviewed_at' => $decided ? now()->subDays(2) : null,
                    'decision_note' => $note,
                    'estimated_minutes' => $minutes,
                ],
            );
        }
    }

    private function projectRequests(Workspace $workspace, User $requester, User $supervisor, User $manager): void
    {
        foreach ([
            [
                'Supplier self-service portal', ProjectRequestStatus::PENDING_MEETING,
                'Suppliers email us for order status and two people answer those emails all day.',
                'A portal where a supplier signs in and sees their own orders and invoices.',
                false, false, false,
            ],
            [
                'Warehouse mobile scanning', ProjectRequestStatus::PENDING_SPV,
                'Stock-take is done on paper and typed in afterwards, which is where the errors come from.',
                'A handheld scanner app that writes straight into Inventory Core.',
                true, false, false,
            ],
            [
                'Unified customer master', ProjectRequestStatus::PENDING_MANAGER,
                'Three systems hold customer records and none of them agree with each other.',
                'One customer record the other systems read from.',
                true, true, false,
            ],
            [
                'Automated month-end close', ProjectRequestStatus::PENDING_MANAGER,
                'Month-end close takes six working days and blocks the whole finance team.',
                'The repetitive parts of the close run on their own overnight.',
                true, true, true,
            ],
        ] as [$title, $status, $benefit, $concept, $metHeld, $spvSigned, $approve]) {
            // Same rule as the feature requests above: create once, never rewind. A row that
            // already exists has a history — signatures, a project, a breakdown — and the
            // fixture has no business overwriting any of it.
            $request = ProjectRequest::firstOrCreate(
                ['workspace_id' => $workspace->id, 'title' => $title],
                [
                    'requester_id' => $requester->id,
                    'benefit' => $benefit,
                    'concept' => $concept,
                    'business_process' => 'Today the work is manual: someone receives the request, checks it by hand, and passes it on by email.',
                    'flow' => 'Request raised → checked → approved → recorded → reported at month end.',
                    'target_date' => now()->addMonths(3)->toDateString(),
                    'status' => $status,
                    'meeting_held_at' => $metHeld ? now()->subDays(5) : null,
                    'meeting_note' => $metHeld ? 'Scope agreed. Phase one covers the reporting half only.' : null,
                    'spv_id' => $spvSigned ? $supervisor->id : null,
                    'spv_at' => $spvSigned ? now()->subDays(4) : null,
                    'manager_id' => null,
                    'manager_at' => null,
                    'estimated_minutes' => $approve ? 160 * 60 : null,
                ],
            );

            // The second signature has to go through the Action: writing status=approved by
            // hand skips createDeliveryProject and leaves a scheduled request with no project,
            // which is a state the real flow cannot produce.
            if ($approve && $request->wasRecentlyCreated && $request->status === ProjectRequestStatus::PENDING_MANAGER) {
                app(TransitionProjectRequest::class)->handle($request, ProjectRequestStatus::APPROVED, $manager, 'Approved for delivery.');
            }
        }
    }

    private function table(): void
    {
        $this->command?->newLine();
        $this->command?->info('Every account below signs in with the password: password');
        $this->command?->table(
            ['Email', 'Workspace role', 'What they are for'],
            [
                ['owner@example.com', 'owner', 'Everything, including master data'],
                ['manager@example.com', 'admin', 'Master data, settings, members'],
                ['lead@example.com', 'manager', 'Second signature on project requests; PIC of Inventory Core'],
                ['supervisor@example.com', 'supervisor', 'Approves feature requests; first signature on project requests'],
                ['member@example.com', 'member', 'Delivery work only; PIC of Payroll Portal'],
                ['viewer@example.com', 'viewer', 'Read-only on the delivery desk'],
                ['requester@example.com', 'requester · FIN/STF', 'Creates requests that need department approval'],
                ['department-head@example.com', 'requester · FIN/MGR', 'Approves Finance requests; own requests go directly to ITD'],
            ],
        );
    }
}
