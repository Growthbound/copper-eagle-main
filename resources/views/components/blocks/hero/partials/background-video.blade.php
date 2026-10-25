{{--
    A hero's decorative background video, from BlockData::backgroundVideo().
    Included with ['video' => normalized video, 'class' => layout class].

    Muted, looping and inline so phones autoplay it, and hidden from assistive
    technology because the heading and photo carry the meaning. It ships with
    no <source>: resources/js/app.js attaches one after the page is idle, and
    only when the visitor has not asked for reduced motion, so nothing heavy
    loads up front and a reduced-motion visitor sees the poster still. The
    pause control stays hidden until the video is actually playing.
--}}
<video
    class="{{ $class }}"
    data-background-video
    data-src="{{ app(\App\Content\BlockData::class)->assetUrl($video['src']) }}"
    data-type="{{ $video['type'] }}"
    poster="{{ app(\App\Content\BlockData::class)->assetUrl($video['poster']['src']) }}"
    width="{{ $video['poster']['width'] }}"
    height="{{ $video['poster']['height'] }}"
    preload="none"
    autoplay
    muted
    loop
    playsinline
    disablepictureinpicture
    aria-hidden="true"
    tabindex="-1"
></video>
<button
    class="background-video-control"
    type="button"
    data-background-video-control
    aria-label="Pause background video"
    hidden
><span class="background-video-control-icon" aria-hidden="true"></span></button>
