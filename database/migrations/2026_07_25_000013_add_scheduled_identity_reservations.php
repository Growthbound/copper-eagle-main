<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_entries', function (Blueprint $table): void {
            $table->string('scheduled_kind', 32)->nullable()->after('scheduled_revision_id');
            $table->string('scheduled_slug')->nullable()->after('scheduled_kind');
        });

        DB::table('content_entries')
            ->whereNotNull('scheduled_revision_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $entry): void {
                $identity = DB::table('content_revisions')
                    ->where('id', $entry->scheduled_revision_id)
                    ->value('identity');
                $identity = is_string($identity) ? json_decode($identity, true) : $identity;

                DB::table('content_entries')
                    ->where('id', $entry->id)
                    ->update([
                        'scheduled_kind' => is_array($identity)
                            ? ($identity['kind'] ?? $entry->kind)
                            : $entry->kind,
                        'scheduled_slug' => is_array($identity)
                            ? ($identity['slug'] ?? $entry->slug)
                            : $entry->slug,
                    ]);
            });

        Schema::table('content_entries', function (Blueprint $table): void {
            $table->unique(
                ['scheduled_kind', 'scheduled_slug'],
                'content_entries_scheduled_identity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('content_entries', function (Blueprint $table): void {
            $table->dropUnique('content_entries_scheduled_identity_unique');
            $table->dropColumn(['scheduled_kind', 'scheduled_slug']);
        });
    }
};
