<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_revisions')
            && Schema::hasColumn('content_revisions', 'identity')
            && Schema::hasTable('content_entries')) {
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
        }

        if (! Schema::hasTable('api_idempotency_records')) {
            return;
        }

        if (! Schema::hasColumn('api_idempotency_records', 'response_body_ciphertext')) {
            Schema::table('api_idempotency_records', function (Blueprint $table): void {
                $table->longText('response_body_ciphertext')->nullable()->after('response_body');
            });
        }

        DB::table('api_idempotency_records')
            ->where('operation', 'like', '%.preview')
            ->whereNull('response_body_ciphertext')
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                $body = is_string($record->response_body)
                    ? json_decode($record->response_body, true, flags: JSON_THROW_ON_ERROR)
                    : $record->response_body;

                DB::table('api_idempotency_records')
                    ->where('id', $record->id)
                    ->update([
                        'response_body' => json_encode(
                            ['code' => 'encrypted_idempotency_replay'],
                            JSON_THROW_ON_ERROR,
                        ),
                        'response_body_ciphertext' => Crypt::encryptString(json_encode(
                            $body,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                        )),
                    ]);
            });
    }

    public function down(): void
    {
        // This forward-repair migration may run after either historical or
        // current schema definitions. Its backfills are intentionally retained
        // because dropping shared columns would corrupt one of those paths.
    }
};
