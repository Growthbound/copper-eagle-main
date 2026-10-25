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
        Schema::table('api_idempotency_records', function (Blueprint $table): void {
            $table->dropUnique('api_idempotency_key_operation_unique');
            $table->json('response_headers')->nullable()->after('response_body');
            $table->longText('response_body_ciphertext')->nullable()->after('response_body');
        });

        DB::table('api_idempotency_records')
            ->where('expires_at', '<=', now())
            ->delete();

        DB::table('api_idempotency_records')
            ->select('api_key_id', 'idempotency_key_hash')
            ->groupBy('api_key_id', 'idempotency_key_hash')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                $ids = DB::table('api_idempotency_records')
                    ->where('api_key_id', $duplicate->api_key_id)
                    ->where('idempotency_key_hash', $duplicate->idempotency_key_hash)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->pluck('id');

                DB::table('api_idempotency_records')
                    ->whereIn('id', $ids->slice(1)->all())
                    ->delete();
            });

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

        Schema::table('api_idempotency_records', function (Blueprint $table): void {
            $table->unique(
                ['api_key_id', 'idempotency_key_hash'],
                'api_idempotency_key_unique',
            );
        });
    }

    public function down(): void
    {
        DB::table('api_idempotency_records')
            ->whereNotNull('response_body_ciphertext')
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                DB::table('api_idempotency_records')
                    ->where('id', $record->id)
                    ->update([
                        'response_body' => Crypt::decryptString($record->response_body_ciphertext),
                    ]);
            });

        Schema::table('api_idempotency_records', function (Blueprint $table): void {
            $table->dropUnique('api_idempotency_key_unique');
            $table->dropColumn(['response_headers', 'response_body_ciphertext']);
            $table->unique(
                ['api_key_id', 'operation', 'idempotency_key_hash'],
                'api_idempotency_key_operation_unique',
            );
        });
    }
};
