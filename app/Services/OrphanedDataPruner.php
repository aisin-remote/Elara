<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class OrphanedDataPruner
{
    /** @return array<string, int> */
    public function purge(User $user): array
    {
        return $this->run($user);
    }

    /** @return array<string, int> */
    public function prune(): array
    {
        return $this->run();
    }

    /** @return array<string, int> */
    private function run(?User $user = null): array
    {
        $connection = DB::connection();
        $foreignKeys = $this->foreignKeys($connection);

        $this->disableForeignKeys($connection);

        try {
            $changes = $connection->transaction(function () use ($connection, $foreignKeys, $user): array {
                $changes = [];

                if ($user) {
                    $this->deletePolymorphicUserData($connection, $user, $changes);
                    $changes['users.deleted'] = $connection->table('users')->where('id', $user->id)->delete();
                    $user->exists = false;
                }

                do {
                    $passChanges = 0;

                    foreach ($foreignKeys as $foreignKey) {
                        $affected = $this->cleanForeignKey($connection, $foreignKey);

                        if ($affected > 0) {
                            $action = $foreignKey['nullable'] ? 'nullified' : 'deleted';
                            $key = $foreignKey['child_table'].'.'.$foreignKey['child_column'].'.'.$action;
                            $changes[$key] = ($changes[$key] ?? 0) + $affected;
                            $passChanges += $affected;
                        }
                    }

                    $passChanges += $this->deleteOrphanedPolymorphicData($connection, $changes);
                } while ($passChanges > 0);

                return $changes;
            });
        } finally {
            $this->enableForeignKeys($connection);
        }

        ksort($changes);

        return $changes;
    }

    /**
     * @param  array{child_table: string, child_column: string, parent_table: string, parent_column: string, nullable: bool}  $foreignKey
     */
    private function cleanForeignKey(ConnectionInterface $connection, array $foreignKey): int
    {
        $childTable = $this->quote($connection, $foreignKey['child_table']);
        $childColumn = $this->quote($connection, $foreignKey['child_column']);
        $parentTable = $this->quote($connection, $foreignKey['parent_table']);
        $parentColumn = $this->quote($connection, $foreignKey['parent_column']);
        $missingParent = "{$childColumn} IS NOT NULL AND NOT EXISTS (SELECT 1 FROM {$parentTable} WHERE {$parentTable}.{$parentColumn} = {$childTable}.{$childColumn})";

        return $foreignKey['nullable']
            ? $connection->affectingStatement("UPDATE {$childTable} SET {$childColumn} = NULL WHERE {$missingParent}")
            : $connection->affectingStatement("DELETE FROM {$childTable} WHERE {$missingParent}");
    }

    /** @return array<int, array{child_table: string, child_column: string, parent_table: string, parent_column: string, nullable: bool}> */
    private function foreignKeys(ConnectionInterface $connection): array
    {
        if ($connection->getDriverName() === 'sqlite') {
            return $this->sqliteForeignKeys($connection);
        }

        $database = $connection->getDatabaseName();

        return collect($connection->select(
            <<<'SQL'
                SELECT
                    kcu.TABLE_NAME AS child_table,
                    kcu.COLUMN_NAME AS child_column,
                    kcu.REFERENCED_TABLE_NAME AS parent_table,
                    kcu.REFERENCED_COLUMN_NAME AS parent_column,
                    columns.IS_NULLABLE AS is_nullable
                FROM information_schema.KEY_COLUMN_USAGE kcu
                JOIN information_schema.COLUMNS columns
                  ON columns.TABLE_SCHEMA = kcu.TABLE_SCHEMA
                 AND columns.TABLE_NAME = kcu.TABLE_NAME
                 AND columns.COLUMN_NAME = kcu.COLUMN_NAME
                WHERE kcu.CONSTRAINT_SCHEMA = ?
                  AND kcu.REFERENCED_TABLE_SCHEMA = ?
                  AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY kcu.TABLE_NAME, kcu.COLUMN_NAME
                SQL,
            [$database, $database],
        ))->map(fn ($foreignKey) => [
            'child_table' => $foreignKey->child_table,
            'child_column' => $foreignKey->child_column,
            'parent_table' => $foreignKey->parent_table,
            'parent_column' => $foreignKey->parent_column,
            'nullable' => $foreignKey->is_nullable === 'YES',
        ])->all();
    }

    /** @return array<int, array{child_table: string, child_column: string, parent_table: string, parent_column: string, nullable: bool}> */
    private function sqliteForeignKeys(ConnectionInterface $connection): array
    {
        $foreignKeys = [];
        $tables = $connection->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");

        foreach ($tables as $table) {
            $tableName = $table->name;
            $columns = collect($connection->select('PRAGMA table_info('.$this->quote($connection, $tableName).')'))
                ->keyBy('name');

            foreach ($connection->select('PRAGMA foreign_key_list('.$this->quote($connection, $tableName).')') as $foreignKey) {
                $foreignKeys[] = [
                    'child_table' => $tableName,
                    'child_column' => $foreignKey->from,
                    'parent_table' => $foreignKey->table,
                    'parent_column' => $foreignKey->to ?: 'id',
                    'nullable' => ! (bool) $columns->get($foreignKey->from)?->notnull,
                ];
            }
        }

        return $foreignKeys;
    }

    /** @param array<string, int> $changes */
    private function deletePolymorphicUserData(ConnectionInterface $connection, User $user, array &$changes): void
    {
        foreach ($this->polymorphicRelations() as $table => [$typeColumn, $idColumn]) {
            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $affected = $connection->table($table)
                ->where($typeColumn, $user->getMorphClass())
                ->where($idColumn, $user->id)
                ->delete();

            if ($affected > 0) {
                $changes["{$table}.deleted"] = $affected;
            }
        }

        if ($connection->getSchemaBuilder()->hasTable('password_reset_tokens')) {
            $affected = $connection->table('password_reset_tokens')->where('email', $user->email)->delete();

            if ($affected > 0) {
                $changes['password_reset_tokens.deleted'] = $affected;
            }
        }
    }

    /** @param array<string, int> $changes */
    private function deleteOrphanedPolymorphicData(ConnectionInterface $connection, array &$changes): int
    {
        $total = 0;

        foreach ($this->polymorphicRelations() as $table => [$typeColumn, $idColumn]) {
            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $tableName = $this->quote($connection, $table);
            $type = $this->quote($connection, $typeColumn);
            $id = $this->quote($connection, $idColumn);
            $types = $connection->table($table)->whereNotNull($typeColumn)->distinct()->pluck($typeColumn);

            foreach ($types as $morphType) {
                $modelClass = Relation::getMorphedModel($morphType) ?? $morphType;

                if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
                    continue;
                }

                $model = new $modelClass;
                $parentTable = $this->quote($connection, $model->getTable());
                $parentKey = $this->quote($connection, $model->getKeyName());
                $affected = $connection->affectingStatement(
                    "DELETE FROM {$tableName} WHERE {$type} = ? AND {$id} IS NOT NULL AND NOT EXISTS (SELECT 1 FROM {$parentTable} WHERE {$parentTable}.{$parentKey} = {$tableName}.{$id})",
                    [$morphType],
                );

                if ($affected > 0) {
                    $changes["{$table}.deleted"] = ($changes["{$table}.deleted"] ?? 0) + $affected;
                    $total += $affected;
                }
            }
        }

        return $total;
    }

    /** @return array<string, array{string, string}> */
    private function polymorphicRelations(): array
    {
        return [
            'activity_logs' => ['subject_type', 'subject_id'],
            'files' => ['attachable_type', 'attachable_id'],
            'notifications' => ['notifiable_type', 'notifiable_id'],
            'push_subscriptions' => ['subscribable_type', 'subscribable_id'],
            'task_breakdowns' => ['subject_type', 'subject_id'],
            'validation_checkpoints' => ['subject_type', 'subject_id'],
        ];
    }

    private function quote(ConnectionInterface $connection, string $identifier): string
    {
        return $connection->getDriverName() === 'mysql'
            ? '`'.str_replace('`', '``', $identifier).'`'
            : '"'.str_replace('"', '""', $identifier).'"';
    }

    private function disableForeignKeys(ConnectionInterface $connection): void
    {
        $connection->statement($connection->getDriverName() === 'mysql'
            ? 'SET FOREIGN_KEY_CHECKS = 0'
            : 'PRAGMA foreign_keys = OFF');
    }

    private function enableForeignKeys(ConnectionInterface $connection): void
    {
        $connection->statement($connection->getDriverName() === 'mysql'
            ? 'SET FOREIGN_KEY_CHECKS = 1'
            : 'PRAGMA foreign_keys = ON');
    }
}
