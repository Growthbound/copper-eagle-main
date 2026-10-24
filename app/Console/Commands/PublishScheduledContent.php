<?php

namespace App\Console\Commands;

use App\Content\ContentLifecycle;
use App\Models\ContentEntry;
use Illuminate\Console\Command;
use Throwable;

class PublishScheduledContent extends Command
{
    protected $signature = 'content:publish-scheduled';

    protected $description = 'Publish validated content revisions whose schedule is due';

    public function handle(ContentLifecycle $lifecycle): int
    {
        $published = 0;
        $failed = 0;

        ContentEntry::query()
            ->with(['scheduledRevision', 'scheduledBy', 'scheduledByApiKey'])
            ->whereNotNull('scheduled_revision_id')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('id')
            ->eachById(function (ContentEntry $entry) use (
                $lifecycle,
                &$published,
                &$failed,
            ): void {
                if ($entry->scheduledRevision === null || $entry->scheduledBy === null) {
                    $failed++;

                    return;
                }

                try {
                    $lifecycle->publish(
                        $entry,
                        $entry->scheduledRevision,
                        $entry->lock_version,
                        $entry->scheduledBy,
                        $entry->scheduledByApiKey,
                    );
                    $published++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            });

        $this->info("Published {$published} scheduled content ".($published === 1 ? 'entry.' : 'entries.'));

        if ($failed > 0) {
            $this->error("Failed to publish {$failed} scheduled content ".($failed === 1 ? 'entry.' : 'entries.'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
