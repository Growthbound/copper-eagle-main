@php($announcement = App\Support\Announcement::current())

@if ($announcement)
    <x-dynamic-component
        :component="'blocks.announcement.'.$announcement['variant']"
        :message="$announcement['message']"
        :url="$announcement['url']"
        :link-label="$announcement['linkLabel']"
    />
@endif
