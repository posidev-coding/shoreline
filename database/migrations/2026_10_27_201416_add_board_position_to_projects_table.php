<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Order within the project board's stage column. No unique index on (stage, position):
            // trashed projects keep theirs, so restoring one could collide with a newer card.
            $table->flowforgePositionColumn('board_position')->after('stage');
        });

        // Existing jobs line up in each stage in the order they reached it, 65535 apart like Flowforge's own gaps.
        DB::statement(<<<'SQL'
            update projects set board_position = ranked.position
            from (
                select id, row_number() over (partition by stage order by stage_changed_at nulls first, id) * 65535 as position
                from projects
            ) as ranked
            where projects.id = ranked.id
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('board_position');
        });
    }
};
