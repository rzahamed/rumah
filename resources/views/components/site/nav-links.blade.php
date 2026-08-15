{{--
    One rendering of the primary link list, shared by the desktop bar and the
    mobile disclosure so the two can never drift. Whichever copy is
    display:none is removed from the accessibility tree and the tab order, so
    duplicating the markup costs no extra landmarks or tab stops.
--}}
@props(['links', 'current' => null])

<ul {{ $attributes }}>
    @foreach ($links as $link)
        <li>
            {{-- The key must be non-null before it can match: Team is a
                 section link with no page key, and on an unmapped route
                 $current is null too, so a bare equality check would mark it
                 as the current page. --}}
            <a
                class="t-ui text-text-primary hover:text-text-muted"
                href="{{ $link['url'] }}"
                @if ($link['key'] !== null && $link['key'] === $current) aria-current="page" @endif
            >{{ $link['label'] }}</a>
        </li>
    @endforeach
</ul>
