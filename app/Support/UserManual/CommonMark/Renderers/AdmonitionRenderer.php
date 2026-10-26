<?php

namespace App\Support\UserManual\CommonMark\Renderers;

use App\Support\UserManual\CommonMark\Blocks\AdmonitionBlock;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

class AdmonitionRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer)
    {
        if (! $node instanceof AdmonitionBlock) {
            throw new \InvalidArgumentException('Incompatible node type: '.get_class($node));
        }

        $kind = $node->kind;
        $allowed = ['info', 'warning', 'tip', 'danger'];
        $kind = in_array($kind, $allowed, true) ? $kind : 'info';

        $inner = (string) $childRenderer->renderNodes($node->children());

        // Note: inner HTML is produced by CommonMark renderer (already escaped per config).
        return new HtmlElement(
            'div',
            ['class' => 'um-admonition um-admonition--'.Xml::escape($kind)],
            $inner
        );
    }
}
