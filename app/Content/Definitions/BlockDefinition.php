<?php

namespace App\Content\Definitions;

final readonly class BlockDefinition
{
    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<int, array<string, mixed>>  $examples
     */
    public function __construct(
        public string $type,
        public string $component,
        public string $purpose,
        public array $fields,
        public array $examples = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'component' => $this->component,
            'purpose' => $this->purpose,
            'fields' => $this->fields,
            'examples' => $this->examples,
        ];
    }
}
