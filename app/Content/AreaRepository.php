<?php

namespace App\Content;

class AreaRepository extends RecordRepository
{
    public function directory(): string
    {
        return base_path('content/areas');
    }

    protected function make(string $slug, array $data): Record
    {
        return new Area($slug, $data);
    }

    /**
     * Area labels for the footer, replacing the list that used to be typed
     * into the admin.
     *
     * @return array<int, string>
     */
    public function labels(): array
    {
        return $this->all()->map(fn (Area $area): string => $area->label())->all();
    }
}
