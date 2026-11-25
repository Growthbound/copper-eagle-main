<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->uuid('quiz_submission_id')->nullable()->unique();
            $table->string('quiz_inspection_choice', 8)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropUnique(['quiz_submission_id']);
            $table->dropColumn(['quiz_submission_id', 'quiz_inspection_choice']);
        });
    }
};
