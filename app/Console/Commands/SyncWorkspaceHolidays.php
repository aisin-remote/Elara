<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Models\WorkspaceHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SyncWorkspaceHolidays extends Command
{
    protected $signature = 'orbitra:sync-holidays {--year= : Sync one calendar year instead of the current and next years}';

    protected $description = 'Sync Indonesian public holidays into every active workspace';

    public function handle(): int
    {
        $years = $this->years();

        if ($years === null) {
            $this->rememberResult('failed', 'The requested year was invalid.');

            return self::FAILURE;
        }

        $holidays = [];
        $failed = [];

        foreach ($years as $year) {
            $yearHolidays = $this->fetch($year);

            if ($yearHolidays === null) {
                $failed[] = $year;

                continue;
            }

            $holidays += $yearHolidays;
        }

        if ($holidays !== []) {
            $workspaceCount = 0;

            Workspace::query()->select('id')->chunkById(100, function ($workspaces) use ($holidays, &$workspaceCount): void {
                foreach ($workspaces as $workspace) {
                    $workspaceCount++;

                    foreach ($holidays as $date => $name) {
                        WorkspaceHoliday::updateOrCreate(
                            ['workspace_id' => $workspace->id, 'observed_on' => $date],
                            ['name' => $name],
                        );
                    }
                }
            });

            $this->info(sprintf(
                'Synced %d holiday dates into %d workspaces.',
                count($holidays),
                $workspaceCount,
            ));
        }

        if ($failed !== []) {
            $this->error('Holiday sync failed for: '.implode(', ', $failed).'. Existing dates were kept.');
            $this->rememberResult('failed', 'Failed years: '.implode(', ', $failed));

            return self::FAILURE;
        }

        $this->rememberResult('healthy', sprintf('Synced %d holiday dates.', count($holidays)));

        return self::SUCCESS;
    }

    private function rememberResult(string $status, string $message): void
    {
        Cache::forever('system_health.holiday_sync', [
            'status' => $status,
            'message' => $message,
            'at' => now()->toIso8601String(),
        ]);
    }

    /** @return list<int>|null */
    private function years(): ?array
    {
        $requested = $this->option('year');

        if ($requested === null) {
            $today = CarbonImmutable::now('Asia/Jakarta');

            return [$today->year, $today->addYear()->year];
        }

        if (! ctype_digit((string) $requested) || (int) $requested < 2020 || (int) $requested > 2100) {
            $this->error('The year must be between 2020 and 2100.');

            return null;
        }

        return [(int) $requested];
    }

    /** @return array<string, string>|null */
    private function fetch(int $year): ?array
    {
        $url = trim((string) config('services.holidays.url'));

        if ($url === '') {
            $this->error('HOLIDAY_API_URL is empty.');

            return null;
        }

        try {
            $request = Http::acceptJson()
                ->timeout((int) config('services.holidays.timeout', 10))
                ->retry(3, 250, throw: false);
            $caBundle = trim((string) config('services.holidays.ca_bundle'));

            if ($caBundle !== '') {
                if (! is_file($caBundle)) {
                    $this->warn('HOLIDAY_API_CA_BUNDLE does not point to a readable file.');

                    return null;
                }

                $request->withOptions(['verify' => $caBundle]);
            }

            $response = str_contains($url, '{year}')
                ? $request->get(str_replace('{year}', (string) $year, $url))
                : $request->get($url, ['year' => $year]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $payload = $response->successful() ? $response->json() : null;
        $items = is_array($payload) && array_is_list($payload)
            ? $payload
            : (is_array($payload) ? ($payload['data'] ?? null) : null);

        if (! is_array($items)) {
            return null;
        }

        $holidays = [];

        foreach ($items as $item) {
            $date = is_array($item) ? ($item['date'] ?? null) : null;
            $name = is_array($item) ? trim((string) ($item['description'] ?? $item['name'] ?? '')) : '';
            $parsed = is_string($date) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;

            if (! $parsed || $parsed->format('Y-m-d') !== $date || (int) $parsed->format('Y') !== $year || $name === '') {
                continue;
            }

            $names = array_unique([...($holidays[$date] ?? []), $name]);
            $holidays[$date] = $names;
        }

        return collect($holidays)
            ->map(fn (array $names): string => Str::limit(implode(' / ', $names), 120, ''))
            ->all();
    }
}
