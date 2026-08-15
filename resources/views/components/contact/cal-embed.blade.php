{{--
    Paper: Contact Page → "Section / Intro Video" — reserved for the Cal.com
    INLINE EMBED (Paper's still image and "Clarvia Introduction" caption are
    the designer's stand-in for it, not a video). paddingTop 83, paddingLeft
    179 / paddingRight 200 (1061px content, centred), one 1061×581 frame with
    radius 20 that clips its content.

    This component renders ONLY the mount element, #cal-inline, sized to that
    frame. It contains no Cal.com URL, script, iframe or markup: the calendar
    is injected into #cal-inline by the CMS Custom Code snippet the layout
    renders globally (SiteSettings custom code, super-admin managed), so the
    embed's configuration lives entirely in the CMS. While the mount is empty
    the section is hidden by .cal-embed:has(#cal-inline:empty) in app.css —
    the mount is deliberately written with nothing inside it, not even
    whitespace, so :empty holds — and it appears automatically once the
    embed has mounted. The frame keeps Paper's 581px as a minimum so an
    embed that measures taller is never clipped; below desktop it scales by
    Paper's ratio.
--}}
<div class="cal-embed pt-4xl desktop:pt-[83px]">
    <div class="container-site">
        <div class="mx-auto w-full max-w-[1061px] overflow-clip rounded-card">
            <div id="cal-inline" class="aspect-[1061/581] w-full desktop:aspect-auto desktop:min-h-[581px]"></div>
        </div>
    </div>
</div>
