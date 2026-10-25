<?php

namespace App\Content;

class ProcessStepRepository extends RecordRepository
{
    public function directory(): string
    {
        return base_path('content/process');
    }

    protected function make(string $slug, array $data): Record
    {
        return new ProcessStep($slug, $data);
    }
}
