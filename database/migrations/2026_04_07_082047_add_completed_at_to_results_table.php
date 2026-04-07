<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (!Schema::hasColumn('results', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('score');
            }
        });

        if (Schema::hasColumn('results', 'submitted_at') && Schema::hasColumn('results', 'completed_at')) {
            \Illuminate\Support\Facades\DB::table('results')
                ->whereNotNull('submitted_at')
                ->update(['completed_at' => \Illuminate\Support\Facades\DB::raw('submitted_at')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (Schema::hasColumn('results', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
        });
    }
};
