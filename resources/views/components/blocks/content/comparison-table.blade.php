@props([
    'id' => 'comparison',
    'eyebrow' => null,
    'heading',
    'caption',
    'columns' => [],
    'rows' => [],
])

@php
    $validColumns = collect(is_array($columns) ? $columns : [])
        ->filter(fn (mixed $column): bool => is_array($column)
            && is_string($column['key'] ?? null)
            && preg_match('/^[a-z][a-z0-9_]*$/', $column['key']) === 1
            && is_string($column['label'] ?? null)
            && trim($column['label']) !== '')
        ->values();
    $columnKeys = $validColumns->pluck('key')->all();
    $validRows = collect(is_array($rows) ? $rows : [])
        ->filter(fn (mixed $row): bool => is_array($row)
            && is_string($row['label'] ?? null)
            && trim($row['label']) !== ''
            && is_array($row['values'] ?? null))
        ->map(function (array $row) use ($columnKeys): array {
            $values = [];
            foreach ($columnKeys as $key) {
                $values[$key] = is_string($row['values'][$key] ?? null)
                    ? $row['values'][$key]
                    : '—';
            }
            return ['label' => trim($row['label']), 'values' => $values];
        })
        ->values();
@endphp

@if ($validColumns->isNotEmpty() && $validRows->isNotEmpty())
    <section id="{{ $id }}" class="comparison-section section-space" aria-labelledby="{{ $id }}-title">
        <header class="evidence-section-heading">
            @if (filled($eyebrow))
                <p class="eyebrow text-accent">{{ $eyebrow }}</p>
            @endif
            <h2 id="{{ $id }}-title">{{ $heading }}</h2>
        </header>
        <div class="comparison-scroll" tabindex="0">
            <table>
                <caption>{{ $caption }}</caption>
                <thead>
                    <tr>
                        <th scope="col">Criteria</th>
                        @foreach ($validColumns as $column)
                            <th scope="col">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($validRows as $row)
                        <tr>
                            <th scope="row">{{ $row['label'] }}</th>
                            @foreach ($validColumns as $column)
                                <td>{{ $row['values'][$column['key']] }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
