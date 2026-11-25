<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->json('quiz_answers')->nullable();
            $table->timestamp('quiz_report_sent_at')->nullable();
            $table->timestamp('quiz_report_attempted_at')->nullable();
            $table->unsignedTinyInteger('quiz_report_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn(['quiz_answers', 'quiz_report_sent_at', 'quiz_report_attempted_at', 'quiz_report_attempts']);
        });
    }
};
