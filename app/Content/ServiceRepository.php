<?php

namespace App\Content;

class ServiceRepository extends RecordRepository
{
    public function directory(): string
    {
        return base_path('content/services');
    }

    protected function make(string $slug, array $data): Record
    {
        return new Service($slug, $data);
    }

    /**
     * Service names for the enquiry form's dropdown, so the options and the
     * pages can never disagree.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return $this->all()->map(fn (Service $service): string => $service->name())->all();
    }
}
