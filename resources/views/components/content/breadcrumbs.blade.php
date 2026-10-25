@props(['items' => []])

@if ($items !== [])
    <nav class="managed-breadcrumbs" aria-label="Breadcrumb">
        <ol>
            @foreach ($items as $item)
                <li>
                    @if (! $loop->last)
                        <a href="{{ $item['url'] }}">{{ $item['name'] }}</a>
                        <span aria-hidden="true">/</span>
                    @else
                        <span aria-current="page">{{ $item['name'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
