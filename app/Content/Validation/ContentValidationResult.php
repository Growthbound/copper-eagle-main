<?php

namespace App\Content\Validation;

final readonly class ContentValidationResult
{
    /**
     * @param  array<string, array<int, string>>  $errors
     * @param  array<string, array<int, string>>  $warnings
     */
    public function __construct(
        private array $errors = [],
        private array $warnings = [],
    ) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function hasErrors(): bool
    {
        return ! $this->isValid();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array{valid: bool, errors: array<string, array<int, string>>, warnings: array<string, array<int, string>>}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid(),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }

    public function merge(self $other): self
    {
        return new self(
            errors: array_merge_recursive($this->errors, $other->errors),
            warnings: array_merge_recursive($this->warnings, $other->warnings),
        );
    }
}
