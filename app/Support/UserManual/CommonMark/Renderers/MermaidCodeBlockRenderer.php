<?php

namespace App\Support\UserManual\CommonMark\Renderers;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

class MermaidCodeBlockRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        if (! $node instanceof FencedCode) {
            throw new \InvalidArgumentException('Incompatible node type: '.get_class($node));
        }

        $info = trim((string) $node->getInfo());
        if ($info !== 'mermaid') {
            // Let the default renderer handle it by returning null-like? Not supported.
            // This renderer should be registered with low priority only for mermaid blocks.
            return new HtmlElement('pre', [], new HtmlElement('code', [], Xml::escape((string) $node->getLiteral())));
        }

        // Mermaid expects raw text inside .mermaid (not HTML).
        return new HtmlElement('div', ['class' => 'mermaid'], Xml::escape((string) $node->getLiteral()));
    }
}
