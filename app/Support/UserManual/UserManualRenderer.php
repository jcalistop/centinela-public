<?php

namespace App\Support\UserManual;

use App\Support\MarkdownMermaid;
use App\Support\UserManual\CommonMark\Blocks\AdmonitionBlock;
use App\Support\UserManual\CommonMark\Blocks\AdmonitionStartParser;
use App\Support\UserManual\CommonMark\Renderers\AdmonitionRenderer;
use App\Support\UserManual\CommonMark\Renderers\MermaidCodeBlockRenderer;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class UserManualRenderer
{
    /**
     * @return array{html: string, frontMatter: array<string, mixed>}
     */
    public function render(string $markdown): array
    {
        $converter = $this->converter();
        $result = $converter->convert($markdown);

        $frontMatter = [];
        if ($result instanceof RenderedContentWithFrontMatter) {
            $frontMatter = $result->getFrontMatter();
        }

        return [
            'html' => MarkdownMermaid::rewriteAppRelativeUrls($result->getContent()),
            'frontMatter' => is_array($frontMatter) ? $frontMatter : [],
        ];
    }

    private function converter(): MarkdownConverter
    {
        $config = [
            // Security defaults (docs are in-repo, but keep safe anyway)
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FrontMatterExtension);

        // Custom blocks
        $environment->addBlockStartParser(new AdmonitionStartParser);
        $environment->addRenderer(AdmonitionBlock::class, new AdmonitionRenderer);

        // Mermaid: render fenced ```mermaid blocks into <div class="mermaid">...</div>
        $environment->addRenderer(FencedCode::class, new MermaidCodeBlockRenderer, 100);

        return new MarkdownConverter($environment);
    }
}
