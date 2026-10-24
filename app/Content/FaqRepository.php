<?php

namespace App\Content;

use Illuminate\Support\Collection;

class FaqRepository extends RecordRepository
{
    public function directory(): string
    {
        return base_path('content/faqs');
    }

    protected function make(string $slug, array $data): Record
    {
        // The record reads "question"; Record::name() is the shared accessor.
        return new Faq($slug, ['name' => $data['question']] + $data);
    }

    protected function requiredField(): string
    {
        return 'question';
    }

    /**
     * Questions worth showing on a given page.
     *
     * Passing neither a service nor an area returns the general ones, which is
     * what a homepage or a standalone page wants. Passing one narrows to the
     * questions that apply there, general questions included, so a service page
     * still answers "do you give quotes?" without repeating it in every record.
     *
     * @return Collection<int, Faq>
     */
    public function for(?Service $service = null, ?Area $area = null): Collection
    {
        return $this->all()
            ->filter(function (Faq $faq) use ($service, $area): bool {
                if ($service === null && $area === null) {
                    return $faq->isGeneral();
                }

                return $faq->appliesToService($service) && $faq->appliesToArea($area);
            })
            ->values();
    }
}
