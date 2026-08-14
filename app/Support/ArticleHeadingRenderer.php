<?php

namespace App\Support;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Stringable;

/**
 * Clamps every article heading into the 2..4 range.
 *
 * "#" is promoted to h2 because the page's only h1 is the post title, so a
 * body opening with a top-level heading can never introduce a second one.
 * "#####" and deeper collapse to h4, the smallest heading the board
 * defines. Levels 2, 3 and 4 pass through unchanged.
 *
 * Registered above the core heading renderer by App\Support\ArticleBody.
 */
final class ArticleHeadingRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable|string|null
    {
        Heading::assertInstanceOf($node);

        /** @var Heading $node */
        $level = max(2, min(4, $node->getLevel()));

        return new HtmlElement(
            'h'.$level,
            [],
            $childRenderer->renderNodes($node->children()),
        );
    }
}
