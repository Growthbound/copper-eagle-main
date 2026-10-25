<?php

namespace App\Livewire\Admin;

use App\Models\Setting;
use App\Support\Announcement;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Announcement')]
class AnnouncementEditor extends Component
{
    public bool $enabled = false;

    public string $message = '';

    public string $url = '';

    public string $linkLabel = '';

    public string $variant = Announcement::DEFAULT_VARIANT;

    public string $startsAt = '';

    public string $endsAt = '';

    public function mount(): void
    {
        $saved = Setting::values();

        $this->enabled = ($saved[Announcement::PREFIX.'enabled'] ?? '0') === '1';
        $this->message = (string) ($saved[Announcement::PREFIX.'message'] ?? '');
        $this->url = (string) ($saved[Announcement::PREFIX.'url'] ?? '');
        $this->linkLabel = (string) ($saved[Announcement::PREFIX.'link_label'] ?? '');
        $this->variant = Announcement::resolveVariant($saved[Announcement::PREFIX.'variant'] ?? null);
        $this->startsAt = (string) ($saved[Announcement::PREFIX.'starts_at'] ?? '');
        $this->endsAt = (string) ($saved[Announcement::PREFIX.'ends_at'] ?? '');
    }

    public function save(): void
    {
        $this->validate([
            'message' => 'required_if:enabled,true|nullable|string|max:200',
            'url' => 'nullable|url|max:255',
            'linkLabel' => 'nullable|string|max:40',
            'variant' => 'required|string|in:'.implode(',', Announcement::variants()),
            'startsAt' => 'nullable|date',
            'endsAt' => 'nullable|date|after:startsAt',
        ], [
            'message.required_if' => 'Add the message you want to show, or switch the announcement off.',
            'endsAt.after' => 'The end time must be later than the start time.',
        ]);

        Setting::write([
            Announcement::PREFIX.'enabled' => $this->enabled ? '1' : '0',
            Announcement::PREFIX.'message' => $this->message ?: null,
            Announcement::PREFIX.'url' => $this->url ?: null,
            Announcement::PREFIX.'link_label' => $this->linkLabel ?: null,
            Announcement::PREFIX.'variant' => $this->variant,
            Announcement::PREFIX.'starts_at' => $this->startsAt ?: null,
            Announcement::PREFIX.'ends_at' => $this->endsAt ?: null,
        ]);

        session()->flash('status', 'Announcement saved.');
    }

    public function render(): View
    {
        return view('livewire.admin.announcement-editor', [
            'variants' => Announcement::variants(),
            'preview' => Announcement::current(),
            'timezone' => config('app.timezone'),
        ]);
    }
}
