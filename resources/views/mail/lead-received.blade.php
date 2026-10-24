<x-mail::message>
# New enquiry

**{{ $lead->name }}** submitted the form on {{ config('site.name') }}.

@if (filled($lead->phone))
**Phone:** [{{ $lead->phone }}](tel:{{ $lead->phone }})
@endif
@if (filled($lead->email))
**Email:** [{{ $lead->email }}](mailto:{{ $lead->email }})
@endif
@if (filled($lead->service))
**Service:** {{ $lead->service }}
@endif
**Received:** {{ $lead->created_at?->format('M j, Y \a\t g:ia') }}

@if (filled($lead->message))
**What they said**

> {{ $lead->message }}
@endif

<x-mail::button :url="$adminUrl">
Open the lead inbox
</x-mail::button>

Replying to this email goes straight to {{ filled($lead->email) ? $lead->name : 'nobody — this enquiry left no email address' }}.

Thanks,<br>
{{ config('site.name') }}
</x-mail::message>
