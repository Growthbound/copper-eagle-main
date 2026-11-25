@props(['projectId' => null])

@php($projectId ??= App\Support\Integrations::forPublicPage()['clarity_project_id'] ?? null)
@if ($projectId)
    <meta name="clarity-project-id" content="{{ $projectId }}">
@endif
