<?php

namespace App\Services;

use App\Models\Workspace;

/**
 * The tuning numbers behind scheduling and validation. Defaults live in config so a fresh
 * workspace behaves sensibly; overrides live in the workspace so an admin can change policy
 * without a deploy (PRD-08).
 */
class WorkspaceSettings
{
    public const KEYS = [
        'validation_window_days' => 'Validation window (days)',
        'pic_grace_days' => 'PIC grace period (days)',
        'horizon_days' => 'Scheduling horizon (days)',
    ];

    public const TEXT_KEYS = [
        // Key stays `ai_model`: it is already stored in settings_json on live workspaces, and
        // renaming it would silently drop every override. Only the label is user-facing.
        'ai_model' => 'Task breakdown model',
    ];

    public function validationWindowDays(Workspace $workspace): int
    {
        return $this->intSetting($workspace, 'validation_window_days', 1, 60);
    }

    public function picGraceDays(Workspace $workspace): int
    {
        return $this->intSetting($workspace, 'pic_grace_days', 0, 90);
    }

    public function horizonDays(Workspace $workspace): int
    {
        return $this->intSetting($workspace, 'horizon_days', 7, 365);
    }

    /** The model that produces task breakdowns (PRD-06), overridable per workspace. */
    public function aiModel(Workspace $workspace): string
    {
        $stored = data_get($workspace->settings_json, 'ai_model');

        return filled($stored) ? (string) $stored : (string) config('services.openai.model');
    }

    /** @return array<string, int|string> */
    public function all(Workspace $workspace): array
    {
        return [
            'validation_window_days' => $this->validationWindowDays($workspace),
            'pic_grace_days' => $this->picGraceDays($workspace),
            'horizon_days' => $this->horizonDays($workspace),
            'ai_model' => $this->aiModel($workspace),
        ];
    }

    public function put(Workspace $workspace, array $values): void
    {
        $settings = $workspace->settings_json ?? [];

        foreach (array_keys(self::KEYS) as $key) {
            if (array_key_exists($key, $values)) {
                $settings[$key] = (int) $values[$key];
            }
        }

        foreach (array_keys(self::TEXT_KEYS) as $key) {
            if (array_key_exists($key, $values)) {
                // Blank clears the override rather than storing an empty model id that would
                // make every call fail with an unhelpful message.
                $settings[$key] = blank($values[$key]) ? null : trim((string) $values[$key]);
            }
        }

        $workspace->update(['settings_json' => array_filter($settings, fn ($value) => $value !== null)]);
    }

    private function intSetting(Workspace $workspace, string $key, int $min, int $max): int
    {
        $stored = data_get($workspace->settings_json, $key);
        $value = is_numeric($stored) ? (int) $stored : (int) config("orbitra.requests.{$key}");

        return max($min, min($max, $value));
    }
}
