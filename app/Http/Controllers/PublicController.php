<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Form;
use App\Models\Post;
use App\Models\TeamMember;
use App\Support\BookingUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * The public site's pages: Home, About, Services, Contact and the blog.
 */
class PublicController extends Controller
{
    /** /blogs → default-locale blog index. */
    public function blogIndexRoot(): View
    {
        app()->setLocale(config('platform.default_locale'));

        return view('public.blog.index', $this->blogIndexData());
    }

    /** Localized blog index (/{locale}/blogs). SetLocale middleware has already run. */
    public function blogIndex(string $locale): View
    {
        return view('public.blog.index', $this->blogIndexData());
    }

    /** /blogs/{slug} → default-locale post. */
    public function blogShowRoot(string $slug): View
    {
        app()->setLocale(config('platform.default_locale'));

        return view('public.blog.show', $this->blogShowData($slug));
    }

    /** Localized post (/{locale}/blogs/{slug}). SetLocale middleware has already run. */
    public function blogShow(string $locale, string $slug): View
    {
        return view('public.blog.show', $this->blogShowData($slug));
    }

    /**
     * The blog index: published posts, newest first, paginated at the
     * configured platform.blog_posts_per_page (the setting exists for exactly
     * this listing). The current page is passed on so the layout's canonical
     * and hreflang tags carry ?page=N — LocalizedUrl was built to accept it.
     *
     * @return array{posts: LengthAwarePaginator, calBookingUrl: string|null, canonicalPage: int}
     */
    private function blogIndexData(): array
    {
        $posts = Post::query()
            ->published()
            ->with('category')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate((int) config('platform.blog_posts_per_page', 4));

        return [
            'posts' => $posts,
            'calBookingUrl' => BookingUrl::current(),
            'canonicalPage' => $posts->currentPage(),
        ];
    }

    /**
     * One published post by slug — a draft, scheduled or unknown slug is a
     * 404, never a preview. The layout's canonical/hreflang read the slug
     * from the route themselves.
     *
     * @return array{post: Post, calBookingUrl: string|null}
     */
    private function blogShowData(string $slug): array
    {
        return [
            'post' => Post::query()
                ->published()
                ->with('category')
                ->where('slug', $slug)
                ->firstOrFail(),
            'calBookingUrl' => BookingUrl::current(),
        ];
    }

    /** /contact → default-locale Contact page. */
    public function contactRoot(): View
    {
        app()->setLocale(config('platform.default_locale'));

        return view('public.contact', $this->contactData());
    }

    /** Localized Contact (/{locale}/contact). SetLocale middleware has already run. */
    public function contact(string $locale): View
    {
        return view('public.contact', $this->contactData());
    }

    /**
     * Everything the Contact page renders. The consultation form is the ONE
     * canonical admin-defined form (config platform.contact_form_slug), read
     * only when active — the same lookup PublicFormController performs — so
     * the page renders exactly the fields that endpoint will accept; null
     * means no form is rendered. FAQs are the CMS FAQ module's visible rows.
     * Paper's Contact page has no booking call to action, so no booking URL
     * is resolved here (the layout composer still supplies the header's).
     *
     * @return array{contactForm: Form|null, faqs: Collection<int, Faq>}
     */
    private function contactData(): array
    {
        return [
            'contactForm' => Form::query()
                ->where('slug', (string) config('platform.contact_form_slug', 'contact'))
                ->where('is_active', true)
                ->first(),
            'faqs' => Faq::query()->visible()->get(),
        ];
    }

    /** /services → default-locale Services page. */
    public function servicesRoot(): View
    {
        app()->setLocale(config('platform.default_locale'));

        return view('public.services', $this->servicesData());
    }

    /** Localized Services (/{locale}/services). SetLocale middleware has already run. */
    public function services(string $locale): View
    {
        return view('public.services', $this->servicesData());
    }

    /**
     * Everything the Services page renders. There is no packages/services
     * module in the CMS, so the page's copy is translation content; the only
     * model-backed value is the booking URL behind its calls to action.
     *
     * @return array{calBookingUrl: string|null}
     */
    private function servicesData(): array
    {
        return [
            'calBookingUrl' => BookingUrl::current(),
        ];
    }

    /** Root path → default-locale home. */
    public function root(): View
    {
        app()->setLocale(config('platform.default_locale'));

        return view('public.home', $this->homeData());
    }

    /** Localized home (/{locale}). SetLocale middleware has already run. */
    public function home(string $locale): View
    {
        return view('public.home', $this->homeData());
    }

    /** /about → default-locale About page. */
    public function aboutRoot(): View
    {
        app()->setLocale(config('platform.default_locale'));

        return view('public.about', $this->aboutData());
    }

    /** Localized About (/{locale}/about). SetLocale middleware has already run. */
    public function about(string $locale): View
    {
        return view('public.about', $this->aboutData());
    }

    /**
     * Everything the About page renders; same query-free-Blade contract as
     * homeData(). The team grid reads the existing TeamMember module — the
     * visible members in their configured order — rather than the placeholder
     * people drawn in Paper.
     *
     * @return array{teamMembers: Collection<int, TeamMember>, calBookingUrl: string|null}
     */
    private function aboutData(): array
    {
        return [
            'teamMembers' => TeamMember::query()
                ->visible()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'calBookingUrl' => BookingUrl::current(),
        ];
    }

    /**
     * Everything the home page renders. All model access lives here so the
     * Blade layer stays query-free.
     *
     * The booking URL is passed explicitly even though the layout composer
     * resolves its own copy for the header: a section body is evaluated in the
     * CHILD view's scope, which never sees the layout's composer data. Both
     * calls read through SiteSettings::current(), which memoizes per request,
     * so this costs no extra query.
     *
     * @return array{latestPosts: Collection<int, Post>, calBookingUrl: string|null}
     */
    private function homeData(): array
    {
        return [
            // Paper's Home "Insights" grid holds four article cards. This is
            // NOT the blog index's featured-plus-grid count. Newest first,
            // with id as the stable tiebreak for posts sharing a timestamp.
            'latestPosts' => Post::query()
                ->published()
                ->with('category')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(4)
                ->get(),
            'calBookingUrl' => BookingUrl::current(),
        ];
    }
}
