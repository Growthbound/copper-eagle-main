<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The wording is stored on the lead rather than referenced from settings.
     *
     * Proving consent means showing what this person agreed to on the day they
     * agreed to it. A pointer to the current text is worthless once the text
     * has been edited, which it will be.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->boolean('consent_given')->default(false)->index();
            $table->text('consent_text')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['consent_given']);
            $table->dropColumn(['consent_given', 'consent_text', 'consent_at', 'consent_ip']);
        });
    }
};
