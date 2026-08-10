<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\OrganizationDirectory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class OrganizationDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development'])) {
            throw new RuntimeException('OrganizationDemoSeeder is restricted to local and development environments.');
        }

        if (! config('organization.required')) {
            return;
        }

        $connection = DB::connection(config('organization.connection'));
        $profiles = [
            ['email' => 'member@example.com', 'name' => 'Nadia Putri', 'npk' => 'ORB-DEMO-ITD-STF', 'rank' => 'STF', 'department' => 'ITD'],
            ['email' => 'supervisor@example.com', 'name' => 'Dewi Anggraini', 'npk' => 'ORB-DEMO-ITD-SPV', 'rank' => 'SPV', 'department' => 'ITD'],
            ['email' => 'lead@example.com', 'name' => 'Bagas Nugroho', 'npk' => 'ORB-DEMO-ITD-MGR', 'rank' => 'MGR', 'department' => 'ITD'],
            ['email' => 'requester@example.com', 'name' => 'Sari Lestari', 'npk' => 'ORB-DEMO-FIN-STF', 'rank' => 'STF', 'department' => 'FIN'],
            ['email' => 'department-head@example.com', 'name' => 'Arif Santoso', 'npk' => 'ORB-DEMO-FIN-MGR', 'rank' => 'MGR', 'department' => 'FIN'],
        ];

        $connection->transaction(function () use ($connection, $profiles): void {
            foreach ($profiles as $profile) {
                $rankId = $connection->table('job_ranks')->where('code', $profile['rank'])->value('id');
                $departmentId = $connection->table('departments')->where('code', $profile['department'])->value('id');

                if (! $rankId || ! $departmentId) {
                    throw new RuntimeException("Organization demo reference {$profile['rank']}/{$profile['department']} was not found.");
                }

                $externalUser = $connection->table('users')
                    ->whereRaw('LOWER(email) = ?', [strtolower($profile['email'])])
                    ->first(['id', 'npk']);

                if ($externalUser && $externalUser->npk !== $profile['npk']) {
                    throw new RuntimeException("Refusing to replace non-demo organization user {$profile['email']}.");
                }

                $externalAttributes = [
                    'name' => $profile['name'],
                    'email' => $profile['email'],
                    'npk' => $profile['npk'],
                    'password' => Hash::make('password'),
                    'company' => 'ORBITRA DEMO',
                    'updated_at' => now(),
                ];

                if ($externalUser) {
                    $connection->table('users')->where('id', $externalUser->id)->update($externalAttributes);
                    $userId = $externalUser->id;
                } else {
                    $userId = $connection->table('users')->insertGetId([
                        ...$externalAttributes,
                        'created_at' => now(),
                    ]);
                }

                $connection->table('model_has_job_ranks')->where('model_id', $userId)->delete();
                $connection->table('model_has_job_ranks')->insert(['model_id' => $userId, 'job_rank_id' => $rankId]);
                $connection->table('model_has_departments')->where('model_id', $userId)->delete();
                $connection->table('model_has_departments')->insert(['model_id' => $userId, 'department_id' => $departmentId]);

                if (config('organization.jit_auth')) {
                    User::where('email', $profile['email'])->first()?->forceFill([
                        'auth_source' => 'organization',
                        'organization_user_id' => $userId,
                        'organization_synced_at' => now(),
                        'password' => Str::random(64),
                        'remember_token' => null,
                    ])->save();
                }
            }
        });

        if (config('organization.jit_auth')) {
            $directory = app(OrganizationDirectory::class);

            User::whereIn('email', array_column($profiles, 'email'))
                ->get()
                ->each(fn (User $user) => $directory->syncMembershipRoles($user));
        }
    }
}
