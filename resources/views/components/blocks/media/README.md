# Media

Controlled video sections that remain understandable when playback is
unavailable.

## `video`

Props: `id`, optional `eyebrow`, `heading`, `video`, `poster`, `transcript`,
and optional `caption`.

`video` requires a safe `src` and one of `video/mp4`, `video/webm`, or
`video/ogg`. `poster` uses the shared image contract (`src`, `alt`, `width`,
`height`). A non-empty transcript is mandatory. If any required field is
invalid, the block renders nothing. Do not autoplay, hide controls, or publish
an inaccessible video without a transcript.
