<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TaskProperty;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TaskFieldSchema
{
    public const SYSTEM_FIELDS = [
        'title' => ['name' => 'Name', 'type' => 'text', 'position' => 1024, 'hideable' => false],
        'description' => ['name' => 'Description', 'type' => 'text', 'position' => 2048, 'hideable' => true],
        'due_at' => ['name' => 'Due date', 'type' => 'date', 'position' => 3072, 'hideable' => true],
        'assignees' => ['name' => 'Assignee', 'type' => 'people', 'position' => 4096, 'hideable' => true],
        'priority' => ['name' => 'Priority', 'type' => 'select', 'position' => 5120, 'hideable' => true],
    ];

    public function systemFields(Project $project): Collection
    {
        $saved = $project->task_fields_json ?? [];

        return collect(self::SYSTEM_FIELDS)->map(function (array $field, string $key) use ($saved): array {
            $custom = is_array($saved[$key] ?? null) ? $saved[$key] : [];

            return [
                ...$field,
                'key' => $key,
                'kind' => 'system',
                'name' => filled($custom['name'] ?? null) ? trim((string) $custom['name']) : $field['name'],
                'visible' => $field['hideable'] ? (bool) ($custom['visible'] ?? true) : true,
            ];
        })->values();
    }

    public function visibleFields(Project $project, ?Collection $properties = null): Collection
    {
        $system = $this->systemFields($project)->where('visible', true);
        $custom = ($properties ?? $project->taskProperties()->active()->get())
            ->map(fn (TaskProperty $property): array => [
                'key' => $property->public_id,
                'kind' => 'custom',
                'name' => $property->name,
                'type' => $property->type->value,
                'position' => 5120 + $property->position,
                'visible' => true,
                'hideable' => true,
                'property' => $property,
            ]);

        return $system->concat($custom)->sortBy('position')->values();
    }

    public function updateSystemField(Project $project, string $key, string $name, bool $visible): void
    {
        $definition = self::SYSTEM_FIELDS[$key] ?? null;

        if ($definition === null) {
            throw ValidationException::withMessages(['field' => 'Choose a valid task field.']);
        }

        $this->ensureUniqueName($project, $name, $key);
        $settings = $project->task_fields_json ?? [];
        $settings[$key] = [
            'name' => trim($name),
            'visible' => $definition['hideable'] ? $visible : true,
        ];
        $project->update(['task_fields_json' => $settings]);
    }

    public function ensureUniqueName(Project $project, string $name, ?string $exceptSystemKey = null, ?TaskProperty $exceptProperty = null): void
    {
        $normalized = mb_strtolower(trim($name));
        $systemMatch = $this->systemFields($project)->contains(
            fn (array $field): bool => $field['key'] !== $exceptSystemKey && mb_strtolower($field['name']) === $normalized,
        );
        $customMatch = $project->taskProperties()
            ->active()
            ->when($exceptProperty, fn ($query) => $query->whereKeyNot($exceptProperty->id))
            ->get(['name'])
            ->contains(fn (TaskProperty $property): bool => mb_strtolower($property->name) === $normalized);

        if ($systemMatch || $customMatch) {
            throw ValidationException::withMessages(['name' => 'This project already has a field with that name.']);
        }
    }
}
