<?php

namespace App\Livewire\Admin;

use App\Enums\MediaKind;
use App\Models\Media;
use App\Models\MediaUsage;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('components.layouts.admin')]
#[Title('Media')]
class MediaLibrary extends Component
{
    use WithFileUploads, WithPagination;

    /** @var array<string, array<int, string>> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        'image/gif' => ['gif'],
        'video/mp4' => ['mp4'],
    ];

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    /** @var array<int, string|null> */
    public array $alt = [];

    /** @var array<int, string|null> */
    public array $title = [];

    /** @var array<int, string|null> */
    public array $handle = [];

    public string $kindFilter = 'all';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedUploads(): void
    {
        $this->validate([
            'uploads.*' => [
                'file',
                'max:25600',
                function (string $attribute, mixed $upload, callable $fail): void {
                    if (! $upload instanceof TemporaryUploadedFile || $this->inspectUpload($upload) === null) {
                        $fail('Upload a verified JPG, PNG, WebP, AVIF, GIF, or MP4 file whose extension matches its contents.');
                    }
                },
            ],
        ], [
            'uploads.*.max' => 'Each media file must be 25 MB or smaller.',
        ]);

        foreach ($this->uploads as $upload) {
            $this->store($upload);
        }

        $this->uploads = [];

        session()->flash('status', 'Upload complete.');
    }

    public function updatedKindFilter(): void
    {
        if (! in_array($this->kindFilter, ['all', MediaKind::Image->value, MediaKind::Video->value], true)) {
            $this->kindFilter = 'all';
        }

        $this->resetPage();
    }

    public function saveDetails(int $mediaId): void
    {
        $media = Media::query()->findOrFail($mediaId);

        $this->title[$mediaId] = $this->normalize($this->title[$mediaId] ?? null);
        $this->alt[$mediaId] = $this->normalize($this->alt[$mediaId] ?? null);
        $this->handle[$mediaId] = $this->normalize($this->handle[$mediaId] ?? null);

        $validated = $this->validate([
            'title.'.$mediaId => ['nullable', 'string', 'max:255'],
            'alt.'.$mediaId => ['nullable', 'string', 'max:255'],
            'handle.'.$mediaId => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('media', 'handle')->ignore($media->id),
            ],
        ], [
            'handle.'.$mediaId.'.regex' => 'Use lowercase letters, numbers, and single hyphens only.',
            'handle.'.$mediaId.'.unique' => 'That handle is already assigned to another media item.',
        ]);

        $updated = DB::transaction(function () use ($mediaId, $validated): bool {
            $stored = Media::query()
                ->lockForUpdate()
                ->findOrFail($mediaId);

            if ($stored->alt !== null
                && ($validated['alt'][$mediaId] ?? null) === null
                && MediaUsage::query()->where('media_id', $stored->id)->exists()) {
                return false;
            }

            $stored->update([
                'title' => $validated['title'][$mediaId] ?? null,
                'alt' => $validated['alt'][$mediaId] ?? null,
                'handle' => $validated['handle'][$mediaId] ?? null,
            ]);

            return true;
        });

        if (! $updated) {
            $this->addError(
                'alt.'.$mediaId,
                'Alt text cannot be cleared while this media is used by managed content. Replace the reference first.',
            );

            return;
        }

        session()->flash('status', 'Media details saved.');
    }

    public function delete(int $mediaId): void
    {
        $result = DB::transaction(function () use ($mediaId): array {
            $media = Media::query()
                ->lockForUpdate()
                ->findOrFail($mediaId);

            if ((int) Setting::read('site.logo_media_id') === $media->id) {
                return [
                    'error' => 'This image is the active brand logo. Choose another logo in Site details before deleting it.',
                ];
            }

            $usages = MediaUsage::query()
                ->with('revision.entry')
                ->where('media_id', $media->id)
                ->get();

            if ($usages->isNotEmpty()) {
                $titles = $usages
                    ->map(fn (MediaUsage $usage): ?string => $usage->revision?->entry?->title)
                    ->filter()
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    'error' => 'This media is still used by '.($titles !== '' ? $titles : 'managed content').'. Replace the media reference before deleting it.',
                ];
            }

            $path = $media->path;
            $media->delete();

            return ['path' => $path];
        });

        if (isset($result['error'])) {
            $this->addError(
                'delete',
                $result['error'],
            );

            return;
        }

        $disk = Storage::disk('public');
        $path = $result['path'];

        if ($disk->exists($path) && ! $disk->delete($path)) {
            Log::warning('A deleted media record left an orphaned stored file.', [
                'media_id' => $mediaId,
                'path' => $path,
            ]);
            $this->addError(
                'delete',
                'The media record was removed, but stored file cleanup failed.',
            );
        }

        session()->flash('status', 'Media deleted.');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $media = Media::query()
            ->search($this->search)
            ->when(
                $this->kindFilter !== 'all',
                fn ($query) => $query->where('kind', $this->kindFilter),
            )
            ->latest()
            ->paginate(24);

        foreach ($media as $item) {
            $this->title[$item->id] ??= $item->title;
            $this->alt[$item->id] ??= $item->alt;
            $this->handle[$item->id] ??= $item->handle;
        }

        $sourceUrls = $media->mapWithKeys(fn (Media $item): array => [
            $item->id => $this->displaySourceUrl($item->source_url),
        ]);

        return view('livewire.admin.media-library', [
            'media' => $media,
            'sourceUrls' => $sourceUrls,
            // Distinguishes "nothing uploaded yet" from "nothing matched", so
            // the empty state can prompt the right next step.
            'libraryIsEmpty' => trim($this->search) === ''
                && $this->kindFilter === 'all'
                && $media->isEmpty(),
        ]);
    }

    private function store(TemporaryUploadedFile $upload): void
    {
        $inspection = $this->inspectUpload($upload);

        if ($inspection === null) {
            return;
        }

        $path = $upload->store('media', 'public');

        if ($path === false) {
            return;
        }

        Media::query()->create([
            'path' => $path,
            'original_name' => $upload->getClientOriginalName(),
            'mime_type' => $inspection['mime_type'],
            'size' => $upload->getSize(),
            'width' => $inspection['width'],
            'height' => $inspection['height'],
            'kind' => $inspection['kind'],
        ]);
    }

    /** @return array{kind: MediaKind, mime_type: string, width: int|null, height: int|null}|null */
    private function inspectUpload(TemporaryUploadedFile $upload): ?array
    {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($upload->getRealPath());
        $extension = strtolower($upload->getClientOriginalExtension());

        if (! is_string($mimeType)
            || ! isset(self::MIME_EXTENSIONS[$mimeType])
            || ! in_array($extension, self::MIME_EXTENSIONS[$mimeType], true)) {
            return null;
        }

        if ($mimeType === 'video/mp4') {
            return [
                'kind' => MediaKind::Video,
                'mime_type' => $mimeType,
                'width' => null,
                'height' => null,
            ];
        }

        $dimensions = @getimagesize($upload->getRealPath());

        if ($dimensions === false) {
            return null;
        }

        return [
            'kind' => MediaKind::Image,
            'mime_type' => $mimeType,
            'width' => $dimensions[0],
            'height' => $dimensions[1],
        ];
    }

    private function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function displaySourceUrl(?string $sourceUrl): ?string
    {
        if ($sourceUrl === null) {
            return null;
        }

        $parts = parse_url($sourceUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return 'Source URL unavailable';
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';

        return $parts['scheme'].'://'.$parts['host'].$port.$path;
    }
}
