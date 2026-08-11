<?php

use App\Enums\ProjectMemberRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A system serves more than one department, and each department answers to its own PIC.
 *
 * The department therefore belongs to the assignment, not to the system: one Avicenna, with a
 * PIC for PPIC and another for Produksi. Holding it on the system forced a second Avicenna to
 * exist, which would have split its board, its tasks, and its history in two.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarded because the first attempt got this far and then failed on the index below:
        // MySQL does not roll DDL back, so the columns are already there on a retry.
        if (! Schema::hasColumn('project_members', 'organization_department_id')) {
            Schema::table('project_members', function (Blueprint $table) {
                // No foreign key: departments live in a PostgreSQL database owned by another
                // application. The code travels beside the id so a screen can still print a
                // label when that database is unreachable.
                $table->unsignedBigInteger('organization_department_id')->nullable()->after('role');
                $table->string('organization_department_code', 32)->nullable()->after('organization_department_id');
                $table->index('organization_department_id');
            });
        }

        // One person holding two departments on the same system is a real arrangement in a
        // small team, so the department joins the key rather than the pair standing alone.
        //
        // Created before the old one is dropped, and in its own statement. The old unique is
        // also the index the project_id foreign key relies on, and MySQL refuses to drop an
        // index a foreign key needs; the new unique starts with project_id too, so once it
        // exists the constraint is covered and the old one can go. SQLite does not enforce
        // this, which is why the test suite was happy and MySQL was not.
        Schema::table('project_members', function (Blueprint $table) {
            $table->unique(
                ['project_id', 'user_id', 'organization_department_id'],
                'project_members_project_user_department_unique'
            );
        });

        Schema::table('project_members', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'user_id']);
        });

        $this->moveDepartmentsDown();

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['organization_department_id']);
            $table->dropColumn(['organization_department_id', 'organization_department_code']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('organization_department_id')->nullable()->after('type');
            $table->string('organization_department_code', 32)->nullable()->after('organization_department_id');
            $table->index('organization_department_id');
        });

        // Only the first assignment survives the trip back, because the old shape could hold
        // exactly one. Anything beyond it is lost — that is what going back to one department
        // per system means.
        foreach (DB::table('project_members')->whereNotNull('organization_department_id')->orderBy('id')->get() as $row) {
            DB::table('projects')->where('id', $row->project_id)->whereNull('organization_department_id')->update([
                'organization_department_id' => $row->organization_department_id,
                'organization_department_code' => $row->organization_department_code,
            ]);
        }

        Schema::table('project_members', function (Blueprint $table) {
            $table->dropUnique('project_members_project_user_department_unique');
            $table->dropIndex(['organization_department_id']);
            $table->dropColumn(['organization_department_id', 'organization_department_code']);
        });

        Schema::table('project_members', function (Blueprint $table) {
            $table->unique(['project_id', 'user_id']);
        });
    }

    /**
     * The system's department lands on the manager that pic() already resolved to — the first
     * one by id — so today's PIC keeps exactly the department it had. Later managers are left
     * alone: they were never the PIC, and inheriting the department would invent a second one.
     *
     * Written in PHP rather than one UPDATE ... JOIN because the test database is SQLite and
     * does not accept that syntax.
     */
    private function moveDepartmentsDown(): void
    {
        $projects = DB::table('projects')->whereNotNull('organization_department_id')
            ->select(['id', 'organization_department_id', 'organization_department_code'])
            ->get();

        foreach ($projects as $project) {
            $firstManager = DB::table('project_members')
                ->where('project_id', $project->id)
                ->where('role', ProjectMemberRole::MANAGER->value)
                ->orderBy('id')
                ->value('id');

            if ($firstManager === null) {
                continue;
            }

            DB::table('project_members')->where('id', $firstManager)->update([
                'organization_department_id' => $project->organization_department_id,
                'organization_department_code' => $project->organization_department_code,
            ]);
        }
    }
};
