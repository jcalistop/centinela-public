<?php

namespace App\Support\UserManual\CommonMark\Blocks;

use League\CommonMark\Node\Block\AbstractBlock;

class AdmonitionBlock extends AbstractBlock
{
    public function __construct(public readonly string $kind)
    {
        parent::__construct();
    }
}
