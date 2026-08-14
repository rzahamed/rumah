<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\MarkdownConverter;
use Throwable;

/**
 * Renders a post body (Markdown, authored in the panel's body textarea)
 * into the article markup the board specifies: paragraphs, h2/h3 headings
 * and bullet lists inside the 596px reading column.
 *
 * Safety is structural, not a filter pass:
 *
 * - 'html_input' => 'escape' means raw HTML in a stored body is rendered as
 *   visible text, never as markup. There is no sanitizer to bypass because
 *   no author-supplied HTML is ever interpreted.
 * - 'allow_unsafe_links' => false drops javascript:, data: and vbscript:
 *   destinations from links and images.
 * - 'max_nesting_level' bounds pathological nesting.
 * - Heading levels are clamped into 2..4 by ArticleHeadingRenderer, so a
 *   body that starts with "# Title" can never introduce a second <h1> on a
 *   page whose only h1 is the post title, and never skips down to h5/h6.
 *
 * The return value is an HtmlString: the one deliberate act of trusting
 * this output happens here, so views need no raw-echo syntax.
 */
class ArticleBody
{
    private const MAX_NESTING_LEVEL = 20;

    public static function render(?string $markdown): HtmlString
    {
        if (! is_string($markdown) || trim($markdown) === '') {
            return new HtmlString('');
        }

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => self::MAX_NESTING_LEVEL,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        // Priority above the core renderer (registered at 0) so this one wins.
        $environment->addRenderer(Heading::class, new ArticleHeadingRenderer, 10);

        try {
            $html = (new MarkdownConverter($environment))->convert($markdown)->getContent();
        } catch (Throwable) {
            // A body that cannot be converted must not take the page down.
            // Nothing is logged: the body is CMS content and could carry
            // anything an author typed.
            return new HtmlString('');
        }

        return new HtmlString($html);
    }
}
