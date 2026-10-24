<?php

namespace App\Console\Commands;

use App\Models\ApiIdempotencyRecord;
use App\Models\ContentPreviewToken;
use Illuminate\Console\Command;

class PruneExpiredContentCredentials extends Command
{
    protected $signature = 'content:prune-expired';

    protected $description = 'Delete expired API idempotency records and unusable preview tokens';

    public function handle(): int
    {
        $idempotencyRecords = ApiIdempotencyRecord::query()
            ->where('expires_at', '<=', now())
            ->delete();
        $previewTokens = ContentPreviewToken::query()
            ->where(function ($query): void {
                $query->where('expires_at', '<=', now())
                    ->orWhereNotNull('revoked_at');
            })
            ->delete();

        $this->info("Pruned {$idempotencyRecords} idempotency records and {$previewTokens} preview tokens.");

        return self::SUCCESS;
    }
}
