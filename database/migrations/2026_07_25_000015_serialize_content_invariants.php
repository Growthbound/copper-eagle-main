<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_idempotency_records')
            && Schema::hasColumn('api_idempotency_records', 'response_body_ciphertext')) {
            Schema::table('api_idempotency_records', function (Blueprint $table): void {
                $table->longText('response_body_ciphertext')->nullable()->change();
            });
        }

        $claims = [];

        DB::table('content_entries')
            ->orderBy('id')
            ->get(['id', 'kind', 'slug', 'scheduled_kind', 'scheduled_slug'])
            ->each(function (object $entry) use (&$claims): void {
                $this->recordClaim(
                    $claims,
                    (int) $entry->id,
                    'current',
                    (string) $entry->kind,
                    (string) $entry->slug,
                );

                if ($entry->scheduled_kind === null || $entry->scheduled_slug === null) {
                    return;
                }

                if ($entry->scheduled_kind === $entry->kind
                    && $entry->scheduled_slug === $entry->slug) {
                    return;
                }

                $this->recordClaim(
                    $claims,
                    (int) $entry->id,
                    'scheduled',
                    (string) $entry->scheduled_kind,
                    (string) $entry->scheduled_slug,
                );
            });

        Schema::create('content_identity_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_entry_id')->constrained()->cascadeOnDelete();
            $table->string('claim_type', 16);
            $table->string('kind', 32);
            $table->string('slug');
            $table->timestamps();

            $table->unique(
                ['kind', 'slug'],
                'content_identity_claims_identity_unique',
            );
            $table->unique(
                ['content_entry_id', 'claim_type'],
                'content_identity_claims_entry_type_unique',
            );
        });

        $now = now();
        foreach ($claims as $claim) {
            DB::table('content_identity_claims')->insert([
                ...$claim,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('content_operation_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('content_operation_locks')->insert([
            'name' => 'redirect-graph',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('content_operation_locks');
        Schema::dropIfExists('content_identity_claims');

        // The ciphertext column is intentionally not narrowed. A rollback must
        // not truncate an encrypted replay that already exceeds TEXT limits.
    }

    /**
     * @param  array<string, array{content_entry_id: int, claim_type: string, kind: string, slug: string}>  $claims
     */
    private function recordClaim(
        array &$claims,
        int $entryId,
        string $claimType,
        string $kind,
        string $slug,
    ): void {
        $key = $kind."\0".$slug;

        if (isset($claims[$key]) && $claims[$key]['content_entry_id'] !== $entryId) {
            throw new RuntimeException(
                "Content identity {$kind}:{$slug} is claimed by entries "
                .$claims[$key]['content_entry_id']." and {$entryId}.",
            );
        }

        $claims[$key] = [
            'content_entry_id' => $entryId,
            'claim_type' => $claimType,
            'kind' => $kind,
            'slug' => $slug,
        ];
    }
};
