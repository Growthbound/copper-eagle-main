<?php

namespace App\Livewire\Admin;

use App\Content\Exceptions\ContentValidationFailed;
use App\Content\RedirectLifecycle;
use App\Content\RedirectValidator;
use App\Models\RedirectRule;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Redirects')]
class Redirects extends Component
{
    public ?int $editingId = null;

    public string $sourcePath = '';

    public string $target = '';

    public int $statusCode = 301;

    public bool $enabled = true;

    public string $notes = '';

    public function save(
        RedirectValidator $validator,
        RedirectLifecycle $lifecycle,
    ): void {
        $rule = $this->editingId === null
            ? null
            : RedirectRule::query()->findOrFail($this->editingId);
        $source = $validator->normalizePath($this->sourcePath);
        $target = $validator->normalizePath($this->target) ?? trim($this->target);
        $attributes = [
            'source_path' => $source ?? trim($this->sourcePath),
            'target' => $target,
            'status_code' => $this->statusCode,
            'enabled' => $this->enabled,
        ];
        $result = $validator->validate($attributes, $rule);

        if ($result->hasErrors()) {
            foreach ($result->errors() as $pointer => $messages) {
                $field = match ($pointer) {
                    '/source_path' => 'sourcePath',
                    '/status_code' => 'statusCode',
                    default => ltrim($pointer, '/'),
                };
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        $owner = $this->owner();

        try {
            if ($rule === null) {
                $lifecycle->create([
                    ...$attributes,
                    'notes' => $this->nullable($this->notes),
                ], $owner);
            } else {
                $lifecycle->update($rule, [
                    ...$attributes,
                    'notes' => $this->nullable($this->notes),
                ], $owner);
            }
        } catch (ContentValidationFailed $failure) {
            $this->addValidationErrors($failure);

            return;
        }

        $this->resetForm();
        session()->flash('status', 'Redirect saved.');
    }

    public function edit(int $ruleId): void
    {
        $rule = RedirectRule::query()->findOrFail($ruleId);
        $this->editingId = $rule->id;
        $this->sourcePath = $rule->source_path;
        $this->target = $rule->target;
        $this->statusCode = $rule->status_code;
        $this->enabled = $rule->enabled;
        $this->notes = $rule->notes ?? '';
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function toggle(
        int $ruleId,
        RedirectValidator $validator,
        RedirectLifecycle $lifecycle,
    ): void {
        $rule = RedirectRule::query()->findOrFail($ruleId);
        $attributes = [
            'source_path' => $rule->source_path,
            'target' => $rule->target,
            'status_code' => $rule->status_code,
            'enabled' => ! $rule->enabled,
        ];
        $result = $validator->validate($attributes, $rule);

        if ($result->hasErrors()) {
            $this->addError('redirect', collect($result->errors())->flatten()->implode(' '));

            return;
        }

        try {
            $rule = $lifecycle->toggle($rule, $attributes['enabled'], $this->owner());
        } catch (ContentValidationFailed $failure) {
            $this->addValidationErrors($failure);

            return;
        }
        session()->flash('status', $rule->enabled ? 'Redirect enabled.' : 'Redirect disabled.');
    }

    public function delete(int $ruleId, RedirectLifecycle $lifecycle): void
    {
        $lifecycle->delete(
            RedirectRule::query()->findOrFail($ruleId),
            $this->owner(),
        );
        $this->resetForm();
        session()->flash('status', 'Redirect deleted.');
    }

    public function render(): View
    {
        return view('livewire.admin.redirects', [
            'rules' => RedirectRule::query()->latest()->get(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'sourcePath', 'target', 'notes']);
        $this->statusCode = 301;
        $this->enabled = true;
        $this->resetErrorBag();
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function addValidationErrors(ContentValidationFailed $failure): void
    {
        foreach ($failure->result->errors() as $pointer => $messages) {
            $field = match ($pointer) {
                '/source_path' => 'sourcePath',
                '/status_code' => 'statusCode',
                default => ltrim($pointer, '/'),
            };
            foreach ($messages as $message) {
                $this->addError($field, $message);
            }
        }
    }

    private function owner(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
