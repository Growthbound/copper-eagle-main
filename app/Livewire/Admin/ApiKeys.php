<?php

namespace App\Livewire\Admin;

use App\Content\Enums\ApiKeyScope;
use App\Models\ApiKey;
use App\Models\User;
use App\Services\ApiKeyManager;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('API keys')]
class ApiKeys extends Component
{
    public string $name = '';

    public ?string $newKey = null;

    public ?int $newKeyId = null;

    /** @var array<int, string> */
    public array $selectedScopes = [ApiKeyScope::MediaImport->value];

    /** @var array<int, array<int, string>> */
    public array $keyScopes = [];

    public function generate(ApiKeyManager $manager): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'selectedScopes' => ['required', 'array', 'min:1'],
            'selectedScopes.*' => [Rule::enum(ApiKeyScope::class)],
        ]);

        $issued = $manager->create(
            $this->owner(),
            $validated['name'],
            $validated['selectedScopes'],
        );

        $this->name = '';
        $this->selectedScopes = [ApiKeyScope::MediaImport->value];
        $this->reveal($issued->key->id, $issued->plaintext);

        session()->flash('status', 'Key generated. Its secret is shown only once.');
    }

    public function rotate(int $keyId, ApiKeyManager $manager): void
    {
        $issued = $manager->rotate($this->ownedKey($keyId));

        $this->reveal($issued->key->id, $issued->plaintext);

        session()->flash('status', 'Old key revoked immediately. Copy the replacement before leaving this page.');
    }

    public function revoke(int $keyId, ApiKeyManager $manager): void
    {
        $manager->revoke($this->ownedKey($keyId));

        if ($this->newKeyId === $keyId) {
            $this->dismissPlaintext();
        }

        session()->flash('status', 'Key revoked immediately.');
    }

    public function saveScopes(int $keyId, ApiKeyManager $manager): void
    {
        $validated = $this->validate([
            'keyScopes.'.$keyId => ['required', 'array', 'min:1'],
            'keyScopes.'.$keyId.'.*' => [Rule::enum(ApiKeyScope::class)],
        ]);

        $manager->updateScopes(
            $this->ownedKey($keyId),
            $validated['keyScopes'][$keyId],
        );

        session()->flash('status', 'API key scopes updated.');
    }

    public function dismissPlaintext(): void
    {
        $this->newKey = null;
        $this->newKeyId = null;
    }

    public function render(): View
    {
        $keys = $this->owner()->apiKeys()->latest()->get();

        foreach ($keys as $key) {
            $this->keyScopes[$key->id] ??= $key->scopes ?? [];
        }

        return view('livewire.admin.api-keys', [
            'keys' => $keys,
            'scopeOptions' => $this->scopeOptions(),
        ]);
    }

    private function owner(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function ownedKey(int $keyId): ApiKey
    {
        return $this->owner()->apiKeys()->findOrFail($keyId);
    }

    private function reveal(int $keyId, string $plaintext): void
    {
        $this->newKeyId = $keyId;
        $this->newKey = $plaintext;
    }

    /**
     * @return array<int, array{value: string, label: string, description: string, high_impact: bool}>
     */
    private function scopeOptions(): array
    {
        return [
            ['value' => ApiKeyScope::MediaImport->value, 'label' => 'Import media', 'description' => 'Upload approved remote media.', 'high_impact' => false],
            ['value' => ApiKeyScope::MediaRead->value, 'label' => 'Read media', 'description' => 'Search the media catalog.', 'high_impact' => false],
            ['value' => ApiKeyScope::ContentRead->value, 'label' => 'Read content', 'description' => 'Read entries, revisions, and schema.', 'high_impact' => false],
            ['value' => ApiKeyScope::ContentDraft->value, 'label' => 'Write drafts', 'description' => 'Create entries and immutable draft revisions.', 'high_impact' => false],
            ['value' => ApiKeyScope::ContentPreview->value, 'label' => 'Issue previews', 'description' => 'Create private revision-bound previews.', 'high_impact' => false],
            ['value' => ApiKeyScope::ContentPublish->value, 'label' => 'Publish content', 'description' => 'Change the public website immediately.', 'high_impact' => true],
            ['value' => ApiKeyScope::ContentSchedule->value, 'label' => 'Schedule publication', 'description' => 'Change the public website at a future time.', 'high_impact' => true],
            ['value' => ApiKeyScope::ContentRollback->value, 'label' => 'Roll back content', 'description' => 'Publish a prior snapshot as a new revision.', 'high_impact' => true],
        ];
    }
}
