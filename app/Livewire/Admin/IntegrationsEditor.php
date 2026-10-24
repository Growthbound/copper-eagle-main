<?php

namespace App\Livewire\Admin;

use App\Support\Integrations;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('Tracking')]
class IntegrationsEditor extends Component
{
    /** @var array<string, string> */
    public array $integrations = [];

    public function mount(): void
    {
        $saved = Integrations::values();

        foreach (array_keys(Integrations::editable()) as $field) {
            $this->integrations[$field] = $saved[$field] ?? '';
        }
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages());

        Integrations::save($this->integrations);

        session()->flash('status', 'Tracking settings saved.');
    }

    public function render(): View
    {
        return view('livewire.admin.integrations-editor', [
            'fields' => config('integrations.fields'),
            'snippets' => config('integrations.snippets'),
        ]);
    }

    /**
     * Blank is always allowed; a present value must match the vendor's format.
     *
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        $rules = [];

        foreach (config('integrations.fields') as $field => $definition) {
            $rules['integrations.'.$field] = ['nullable', 'string', 'max:255', 'regex:'.$definition['pattern']];
        }

        foreach (array_keys(config('integrations.snippets')) as $field) {
            $rules['integrations.'.$field] = ['nullable', 'string', 'max:10000'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        $messages = [];

        foreach (config('integrations.fields') as $field => $definition) {
            $messages['integrations.'.$field.'.regex'] = $definition['pattern_hint'];
        }

        return $messages;
    }
}
