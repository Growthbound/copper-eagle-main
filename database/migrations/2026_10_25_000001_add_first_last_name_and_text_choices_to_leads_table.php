<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * All nullable and never backfilled. `name` stays the full name every
     * reader already uses. Splitting an old name would only guess, and an
     * earlier visitor was never asked the text-message questions. Null means
     * "not asked"; false means the box was shown and left unticked.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('first_name', 120)->nullable();
            $table->string('last_name', 120)->nullable();
            $table->boolean('consent_service')->nullable();
            $table->boolean('consent_marketing')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn(['first_name', 'last_name', 'consent_service', 'consent_marketing']);
        });
    }
};
