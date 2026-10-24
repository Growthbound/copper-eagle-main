<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_preview_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_revision_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('content_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_revision_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('request_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['content_entry_id', 'created_at']);
        });

        Schema::create('redirect_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('source_path')->unique();
            $table->text('target');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('enabled')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->foreignId('content_revision_id')->constrained()->cascadeOnDelete();
            $table->string('json_pointer');
            $table->string('usage_type', 40);
            $table->timestamps();

            $table->unique(
                ['content_revision_id', 'json_pointer', 'usage_type'],
                'media_usages_revision_pointer_type_unique',
            );
            $table->index(['media_id', 'content_revision_id']);
        });

        Schema::create('api_idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->string('operation', 120);
            $table->char('idempotency_key_hash', 64);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->unique(
                ['api_key_id', 'operation', 'idempotency_key_hash'],
                'api_idempotency_key_operation_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotency_records');
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('redirect_rules');
        Schema::dropIfExists('content_audit_events');
        Schema::dropIfExists('content_preview_tokens');
    }
};
