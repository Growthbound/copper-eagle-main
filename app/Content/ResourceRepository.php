<?php

namespace App\Content;

class ResourceRepository extends RecordRepository
{
    public function directory(): string
    {
        return base_path('content/resources');
    }

    protected function make(string $slug, array $data): Record
    {
        return new Resource($slug, $data);
    }
}
