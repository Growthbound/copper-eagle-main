<?php

namespace App\Livewire\Admin;

use App\Content\ContentLifecycle;
use App\Content\ContentSnapshotDiff;
use App\Content\Enums\ContentSource;
use App\Content\Exceptions\ContentConflict;
use App\Content\Exceptions\ContentValidationFailed;
use App\Models\ContentEntry;
use App\Models\ContentRevision;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Content history')]
class ContentHistory extends Component
{
    public int $entryId;

    public int $lockVersion;

    public ?int $fromRevisionId = null;

    public ?int $toRevisionId = null;

    public function mount(ContentEntry $entry): void
    {
        $revisions = $entry->revisions()->latest('revision_number')->get();
        $this->entryId = $entry->id;
        $this->lockVersion = $entry->lock_version;
        $this->toRevisionId = $revisions->first()?->id;
        $this->fromRevisionId = $revisions->skip(1)->first()?->id
            ?? $this->toRevisionId;
    }

    public function rollback(int $revisionId, ContentLifecycle $lifecycle): void
    {
        $entry = $this->entry();
        $revision = $entry->revisions()->findOrFail($revisionId);

        try {
            $rollback = $lifecycle->rollback(
                $entry,
                $revision,
                $this->lockVersion,
                $this->owner(),
                ContentSource::Admin,
            );
            $this->lockVersion = $entry->fresh()->lock_version;
            $this->fromRevisionId = $revision->id;
            $this->toRevisionId = $rollback->id;
            session()->flash('status', "Revision {$revision->revision_number} restored and published.");
        } catch (ContentConflict $conflict) {
            $this->addError('conflict', $conflict->getMessage());
        } catch (ContentValidationFailed $failure) {
            $this->addError(
                'rollback',
                collect($failure->result->errors())->flatten()->implode(' '),
            );
        }
    }

    public function render(ContentSnapshotDiff $diff): View
    {
        $entry = $this->entry()->load([
            'revisions.user',
            'revisions.apiKey',
            'auditEvents.user',
            'auditEvents.apiKey',
        ]);
        $from = $this->revision($entry, $this->fromRevisionId);
        $to = $this->revision($entry, $this->toRevisionId);

        return view('livewire.admin.content-history', [
            'entry' => $entry,
            'revisions' => $entry->revisions->sortByDesc('revision_number')->values(),
            'events' => $entry->auditEvents->sortByDesc('created_at')->values(),
            'changes' => $from !== null && $to !== null
                ? $diff->between(
                    ['identity' => $from->identity, 'payload' => $from->payload],
                    ['identity' => $to->identity, 'payload' => $to->payload],
                )
                : [],
        ]);
    }

    private function revision(ContentEntry $entry, ?int $id): ?ContentRevision
    {
        return $id === null
            ? null
            : $entry->revisions->firstWhere('id', $id);
    }

    private function entry(): ContentEntry
    {
        return ContentEntry::query()->findOrFail($this->entryId);
    }

    private function owner(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
