<?php

namespace App\Content;

use Illuminate\View\ComponentAttributeBag;

/**
 * One section of a page.
 *
 * The type is written as "category/variant" in the page file and resolves to
 * the matching anonymous Blade component in
 * resources/views/components/blocks.
 */
final class Block
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public readonly string $type,
        public readonly array $props = [],
    ) {}

    public function component(): string
    {
        return 'blocks.'.str_replace('/', '.', $this->type);
    }

    /**
     * The path the component is expected to live at, used when reporting a
     * block type that does not exist.
     */
    public function templatePath(): string
    {
        return 'resources/views/components/blocks/'.$this->type.'.blade.php';
    }

    public function exists(): bool
    {
        return file_exists(base_path($this->templatePath()));
    }

    public function attributes(): ComponentAttributeBag
    {
        return new ComponentAttributeBag($this->props);
    }
}
