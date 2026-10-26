<?php

namespace App\Support\UserManual\CommonMark\Blocks;

use League\CommonMark\Parser\Block\AbstractBlockContinueParser;
use League\CommonMark\Parser\Block\BlockContinue;
use League\CommonMark\Parser\Block\BlockContinueParserInterface;
use League\CommonMark\Parser\Block\BlockStart;
use League\CommonMark\Parser\Block\BlockStartParserInterface;
use League\CommonMark\Parser\Cursor;
use League\CommonMark\Parser\MarkdownParserStateInterface;
use League\CommonMark\Util\RegexHelper;

class AdmonitionStartParser implements BlockStartParserInterface
{
    public function tryStart(Cursor $cursor, MarkdownParserStateInterface $parserState): ?BlockStart
    {
        // Match: "::::info" / "::::warning" / "::::tip" / "::::danger"
        if ($cursor->isIndented()) {
            return null;
        }

        $re = '/^:{4}(info|warning|tip|danger)\s*$/';
        $match = RegexHelper::matchFirst($re, $cursor->getLine());
        if (! $match) {
            return null;
        }

        $cursor->advanceToEnd();

        return BlockStart::of(new AdmonitionContinueParser($match[1]))->at($cursor);
    }
}

class AdmonitionContinueParser extends AbstractBlockContinueParser
{
    private AdmonitionBlock $block;

    public function __construct(string $kind)
    {
        $this->block = new AdmonitionBlock($kind);
    }

    public function getBlock(): AdmonitionBlock
    {
        return $this->block;
    }

    public function tryContinue(Cursor $cursor, BlockContinueParserInterface $activeBlockParser): ?BlockContinue
    {
        if ($cursor->isIndented()) {
            return BlockContinue::none();
        }

        if (RegexHelper::matchFirst('/^:{4}\s*$/', $cursor->getLine())) {
            $cursor->advanceToEnd();

            return BlockContinue::finished();
        }

        return BlockContinue::at($cursor);
    }
}
