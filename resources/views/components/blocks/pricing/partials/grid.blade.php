<div class="pricing-grid">
    @foreach ($plans as $plan)
        @include('components.blocks.pricing.partials.card', [
            'plan' => $plan,
            'position' => $loop->iteration,
            'modes' => $modes,
        ])
    @endforeach
</div>
