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
            if (!Schema::hasColumn('results', 'question_ids')) {
                $table->json('question_ids')->nullable()->after('total_questions');
            }
            if (!Schema::hasColumn('results', 'answers')) {
                $table->json('answers')->nullable()->after('question_ids');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            if (Schema::hasColumn('results', 'question_ids')) {
                $table->dropColumn('question_ids');
            }
            if (Schema::hasColumn('results', 'answers')) {
                $table->dropColumn('answers');
            }
        });
    }
};
