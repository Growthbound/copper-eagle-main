<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('kind', 16)->default('image')->index();
            $table->string('title')->nullable();
            $table->string('handle')->nullable()->unique();
            $table->text('source_url')->nullable();
            $table->char('source_hash', 64)->nullable()->unique();
            $table->char('checksum', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropUnique(['handle']);
            $table->dropUnique(['source_hash']);
            $table->dropIndex(['checksum']);
            $table->dropColumn([
                'kind',
                'title',
                'handle',
                'source_url',
                'source_hash',
                'checksum',
            ]);
        });
    }
};
