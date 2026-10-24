<?php

namespace App\Content;

class BenefitRepository extends RecordRepository
{
    public function directory(): string
    {
        return base_path('content/benefits');
    }

    protected function make(string $slug, array $data): Record
    {
        return new Benefit($slug, $data);
    }
}
