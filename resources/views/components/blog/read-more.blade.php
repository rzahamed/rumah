{{--
    Paper: Blog Index → "Button / Read More" (featured card and grid cards
    alike): a 1px midnight-blue pill, padding 9px 16px, gap 12, the 16px/20px
    label and a 22px arrow that points along the reading direction. Not the
    shared .btn-secondary, whose padding is Paper's hero secondary (8px 20px).

    Every card carries one of these, so its accessible name is the label
    plus the visually hidden article title — otherwise assistive technology
    would hear an identical "Read More" for every card.
--}}
@props(['href', 'title'])

<a
    href="{{ $href }}"
    {{ $attributes->merge(['class' => 't-ui inline-flex w-fit items-center gap-sm rounded-pill border border-background-brand px-md py-[9px] text-text-primary transition-colors hover:bg-background-brand hover:text-text-on-brand']) }}
>
    <span>{{ __('blog.index.read_more') }}<span class="sr-only">{{ __('blog.index.read_more_suffix', ['title' => $title]) }}</span></span>
    <x-site.icon.arrow-right class="size-[22px]" />
</a>
