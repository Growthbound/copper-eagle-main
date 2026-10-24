<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_revisions', function (Blueprint $table): void {
            $table->json('identity')->nullable()->after('schema_version');
        });

        DB::table('content_revisions')
            ->whereNull('identity')
            ->orderBy('id')
            ->get()
            ->each(function (object $revision): void {
                $entry = DB::table('content_entries')
                    ->where('id', $revision->content_entry_id)
                    ->first(['kind', 'slug', 'title', 'template']);

                if ($entry === null) {
                    return;
                }

                DB::table('content_revisions')
                    ->where('id', $revision->id)
                    ->update([
                        'identity' => json_encode([
                            'kind' => $entry->kind,
                            'slug' => $entry->slug,
                            'title' => $entry->title,
                            'template' => $entry->template,
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    ]);
            });

        Schema::table('content_entries', function (Blueprint $table): void {
            $table->foreignId('scheduled_by')
                ->nullable()
                ->after('scheduled_for')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('scheduled_by_api_key_id')
                ->nullable()
                ->after('scheduled_by')
                ->constrained('api_keys')
                ->nullOnDelete();
        });

        DB::table('content_entries')
            ->whereNotNull('scheduled_revision_id')
            ->whereNull('scheduled_by')
            ->update(['scheduled_by' => DB::raw('updated_by')]);

        Schema::create('redirect_audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('redirect_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 24);
            $table->string('request_id')->nullable()->index();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();

            $table->index(['redirect_rule_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirect_audit_events');

        Schema::table('content_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scheduled_by_api_key_id');
            $table->dropConstrainedForeignId('scheduled_by');
        });

        Schema::table('content_revisions', function (Blueprint $table): void {
            $table->dropColumn('identity');
        });
    }
};
