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
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'total_questions')) {
                $table->integer('total_questions')->default(0)->after('duration');
            }

            if (!Schema::hasColumn('exams', 'password')) {
                $table->string('password')->nullable()->after('total_questions');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'password')) {
                $table->dropColumn('password');
            }

            if (Schema::hasColumn('exams', 'total_questions')) {
                $table->dropColumn('total_questions');
            }
        });
    }
};
