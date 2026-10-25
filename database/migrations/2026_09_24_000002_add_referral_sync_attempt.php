<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->uuid('sync_attempt_id')->nullable();
            $table->timestamp('sync_started_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table): void {
            $table->dropColumn(['sync_attempt_id', 'sync_started_at']);
        });
    }
};
