{{--
    Paper: Blog Index → "Section / Latest Blog" → "Card / Newsletter". The
    brand surface, radius 20, padding 97px 66px, gap 41: a 32px/120% white
    title max 373; a 16px/145% #C6D2DB body max 383; then the signup — its
    "Subscribe Now." line at 24px/30px white and the field row — rendered by
    the ONE shared newsletter implementation, x-site.newsletter-form, in its
    blog presentation. This component supplies only the surface and the copy
    Paper gives this card (lang/blog.php); the endpoint, CSRF, error bag,
    consent sentence and Turnstile are the shared component's, so this card
    can never drift from the Home one in behaviour.
--}}
<section
    class="flex w-full max-w-[515px] flex-col gap-[41px] rounded-card bg-background-brand px-lg py-xl text-text-on-brand tablet:px-[66px] tablet:py-[97px]"
    aria-labelledby="blog-newsletter-heading"
>
    <h3 id="blog-newsletter-heading" class="t-h2 max-w-[373px]">{{ __('blog.index.newsletter.title') }}</h3>
    <p class="t-ui max-w-[383px] leading-[145%] text-text-on-brand-muted">{{ __('blog.index.newsletter.body') }}</p>

    <x-site.newsletter-form variant="blog" :placeholder="__('blog.index.newsletter.placeholder')">
        <p class="t-card-title leading-[30px] text-text-on-brand">{{ __('blog.index.newsletter.subscribe_now') }}</p>
    </x-site.newsletter-form>
</section>
