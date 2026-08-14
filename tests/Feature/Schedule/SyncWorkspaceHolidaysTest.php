<?php

namespace Tests\Feature\Schedule;

use App\Actions\Workspace\CreateWorkspace;
use App\Models\User;
use App\Models\WorkspaceHoliday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncWorkspaceHolidaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_holidays_are_synced_into_every_workspace_without_duplicates(): void
    {
        $first = $this->workspace('ITD');
        $second = $this->workspace('QAU');

        WorkspaceHoliday::create([
            'workspace_id' => $first->id,
            'observed_on' => '2026-01-01',
            'name' => 'Old name',
        ]);
        WorkspaceHoliday::create([
            'workspace_id' => $first->id,
            'observed_on' => '2026-03-01',
            'name' => 'Company closure',
        ]);

        Http::fake([
            str_replace('{year}', '*', config('services.holidays.url')) => Http::response([
                ['date' => '2026-01-01', 'description' => 'Tahun Baru 2026 Masehi'],
                ['date' => '2026-08-17', 'description' => 'Hari Kemerdekaan Republik Indonesia'],
            ]),
        ]);

        $this->artisan('orbitra:sync-holidays --year=2026')->assertSuccessful();
        $this->artisan('orbitra:sync-holidays --year=2026')->assertSuccessful();

        $this->assertDatabaseCount('workspace_holidays', 5);
        $this->assertDatabaseHas('workspace_holidays', [
            'workspace_id' => $first->id,
            'observed_on' => '2026-01-01',
            'name' => 'Tahun Baru 2026 Masehi',
        ]);
        $this->assertDatabaseHas('workspace_holidays', [
            'workspace_id' => $second->id,
            'observed_on' => '2026-08-17',
            'name' => 'Hari Kemerdekaan Republik Indonesia',
        ]);
        $this->assertDatabaseHas('workspace_holidays', [
            'workspace_id' => $first->id,
            'observed_on' => '2026-03-01',
            'name' => 'Company closure',
        ]);
    }

    public function test_failed_feed_keeps_existing_holidays(): void
    {
        $workspace = $this->workspace('ITD');
        WorkspaceHoliday::create([
            'workspace_id' => $workspace->id,
            'observed_on' => '2026-08-17',
            'name' => 'Existing holiday',
        ]);

        Http::fake([str_replace('{year}', '*', config('services.holidays.url')) => Http::response([], 503)]);

        $this->artisan('orbitra:sync-holidays --year=2026')->assertFailed();

        $this->assertDatabaseHas('workspace_holidays', [
            'workspace_id' => $workspace->id,
            'observed_on' => '2026-08-17',
            'name' => 'Existing holiday',
        ]);
    }

    private function workspace(string $name)
    {
        return app(CreateWorkspace::class)->handle(User::factory()->create(), [
            'name' => $name,
            'timezone' => 'Asia/Jakarta',
            'locale' => 'en',
            'week_start' => 1,
        ]);
    }
}
