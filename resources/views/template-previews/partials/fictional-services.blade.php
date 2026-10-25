@php
    /*
     * Request-scoped preview data only. Eight sample service records carrying
     * the brand line icons, bound in place of the service repository for this
     * one request so the icon strip renders without writing anything to
     * content/services. Public pages read the real service records.
     */
    $fictionalServices = collect([
        ['sample-general-plumbing', 'General Plumbing', 'general-plumbing'],
        ['sample-drain-cleaning', 'Drain Cleaning + Repairs', 'drain-cleaning'],
        ['sample-water-filtration', 'Water Filtration', 'water-filtration'],
        ['sample-water-heaters', 'Water Heaters + Repairs', 'water-heaters'],
        ['sample-steam-showers', 'Steam Showers', 'steam-showers'],
        ['sample-new-construction', 'New Construction + Remodels', 'new-construction'],
        ['sample-gas-lines', 'Gas Lines', 'gas-lines'],
        ['sample-garbage-disposals', 'Garbage Disposals + Appliances', 'garbage-disposals'],
    ])->map(fn (array $service, int $index): \App\Content\Service => new \App\Content\Service($service[0], [
        'name' => $service[1],
        'summary' => 'A fictional service record for the local template preview.',
        'icon' => 'images/brand/icons/'.$service[2].'.png',
        'order' => $index + 1,
    ]));

    app()->instance(
        \App\Content\ServiceRepository::class,
        new class($fictionalServices) extends \App\Content\ServiceRepository
        {
            public function __construct(private readonly \Illuminate\Support\Collection $records) {}

            public function all(): \Illuminate\Support\Collection
            {
                return $this->records->values();
            }

            public function find(string $slug): ?\App\Content\Record
            {
                return $this->records->first(fn (\App\Content\Service $service): bool => $service->slug === $slug);
            }

            public function isEmpty(): bool
            {
                return $this->records->isEmpty();
            }
        },
    );
@endphp
