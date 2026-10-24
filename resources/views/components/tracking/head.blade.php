{{--
    Third-party tags that must load early. Each renders only when its ID is set
    in Admin → Tracking, so an unconfigured site ships no third-party
    JavaScript at all.

    Never included in the admin layout: the back office is not analytics data.
--}}
@php($integrations = App\Support\Integrations::values())

@if (isset($integrations['gtm_container_id']))
    <script>
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','{{ $integrations['gtm_container_id'] }}');
    </script>
@endif

@if (isset($integrations['ga4_measurement_id']))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $integrations['ga4_measurement_id'] }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $integrations['ga4_measurement_id'] }}');
    </script>
@endif

@if (isset($integrations['clarity_project_id']))
    <script>
        (function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
        t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
        y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
        })(window,document,"clarity","script","{{ $integrations['clarity_project_id'] }}");
    </script>
@endif

@if (isset($integrations['meta_pixel_id']))
    <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
        n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
        document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '{{ $integrations['meta_pixel_id'] }}');
        fbq('track', 'PageView');
    </script>
    <noscript><img height="1" width="1" style="display:none" alt=""
        src="https://www.facebook.com/tr?id={{ $integrations['meta_pixel_id'] }}&ev=PageView&noscript=1"></noscript>
@endif

@if (isset($integrations['posthog_key']))
    <script>
        !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){
        function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]);
        t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}
        (p=t.createElement("script")).type="text/javascript",p.async=!0,
        p.src=s.api_host+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);
        var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){
        var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){
        return u.toString(1)+".people (stub)"},o="capture identify alias people.set people.set_once set_config register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset isFeatureEnabled onFeatureFlags getFeatureFlag getFeatureFlagPayload reloadFeatureFlags group updateEarlyAccessFeatureEnrollment getEarlyAccessFeatures getActiveMatchingSurveys getSurveys onSessionId".split(" "),
        n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
        posthog.init('{{ $integrations['posthog_key'] }}', {api_host: '{{ $integrations['posthog_host'] ?? 'https://us.i.posthog.com' }}'});
    </script>
@endif

@if (isset($integrations['head_snippet']))
    {!! $integrations['head_snippet'] !!}
@endif
