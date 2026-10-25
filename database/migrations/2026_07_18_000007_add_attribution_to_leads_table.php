<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Discrete columns rather than one JSON blob: the point of this data is to
     * answer "which campaign produced this job", which means grouping and
     * filtering on it.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->string('utm_source')->nullable()->index();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable()->index();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('gclid')->nullable();
            $table->string('fbclid')->nullable();
            $table->string('msclkid')->nullable();
            $table->string('referrer', 512)->nullable();
            $table->string('landing_page', 512)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex(['utm_source']);
            $table->dropIndex(['utm_campaign']);
            $table->dropColumn([
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
                'gclid', 'fbclid', 'msclkid', 'referrer', 'landing_page',
            ]);
        });
    }
};
