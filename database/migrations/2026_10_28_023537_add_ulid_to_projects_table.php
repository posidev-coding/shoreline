<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The portal's project URLs use this, so they don't count up from 1 or reveal how many jobs there are.
        Schema::table('projects', function (Blueprint $table) {
            $table->ulid()->nullable()->after('id');
        });

        // By id, not by page: each update takes a row out of whereNull('ulid'), which would shift the pages.
        // Lowercase, like the ones HasUlids makes.
        DB::table('projects')->whereNull('ulid')->eachById(function (object $project): void {
            DB::table('projects')->where('id', $project->id)->update(['ulid' => strtolower((string) Str::ulid())]);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->ulid()->nullable(false)->change();
            $table->unique('ulid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('ulid');
        });
    }
};
