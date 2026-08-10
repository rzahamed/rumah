{{--
    The ONLY raw-output sink in the codebase. $snippet comes exclusively
    from SiteSettings (super-admin-only trusted HTML) via the layout
    composer, and only the PUBLIC layout includes this partial. The value
    is a runtime variable: Blade never compiles variable contents, so
    directives or PHP inside a snippet render as literal text of the
    snippet itself, never executed server-side.
--}}
@if (filled($snippet ?? null)){!! $snippet !!}
@endif
