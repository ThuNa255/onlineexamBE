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
            if (!Schema::hasColumn('results', 'total_correct')) {
                $table->integer('total_correct')->default(0)->after('score');
            }
            if (!Schema::hasColumn('results', 'total_questions')) {
                $table->integer('total_questions')->default(0)->after('total_correct');
            }
            if (!Schema::hasColumn('results', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('total_questions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (Schema::hasColumn('results', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
            if (Schema::hasColumn('results', 'total_questions')) {
                $table->dropColumn('total_questions');
            }
            if (Schema::hasColumn('results', 'total_correct')) {
                $table->dropColumn('total_correct');
            }
        });
    }
};
