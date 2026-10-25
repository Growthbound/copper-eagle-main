<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('high_level_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('location_id', 80);
            $table->string('location_name');
            $table->text('token')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // One delivery per local lead, retained for that lead's lifetime.
        // Deleting the lead also removes its delivery record, never its CRM contact.
        Schema::create('high_level_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('location_id', 80);
            $table->uuid('reference')->unique();
            $table->string('status', 30)->default('pending');
            $table->string('contact_id', 80)->nullable();
            $table->timestamp('tagged_at')->nullable();
            $table->timestamp('note_started_at')->nullable();
            $table->string('note_id', 80)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->uuid('worker_token')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('high_level_deliveries');
        Schema::dropIfExists('high_level_connections');
    }
};
