<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 32);
            $table->string('slug');
            $table->string('title');
            $table->string('template', 32)->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['kind', 'slug']);
            $table->index(['status', 'scheduled_for']);
        });

        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('payload');
            $table->string('summary')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 16);
            $table->timestamps();

            $table->unique(['content_entry_id', 'revision_number']);
            $table->index(['content_entry_id', 'created_at']);
        });

        Schema::table('content_entries', function (Blueprint $table): void {
            $table->foreignId('published_revision_id')
                ->nullable()
                ->constrained('content_revisions')
                ->nullOnDelete();
            $table->foreignId('scheduled_revision_id')
                ->nullable()
                ->constrained('content_revisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('content_entries');
        Schema::enableForeignKeyConstraints();
    }
};
