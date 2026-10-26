<?php

namespace App\Support\UserManual\Dto;

class UserManualPage
{
    /**
     * @param  array<string, mixed>  $frontMatter
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly array $frontMatter,
        public readonly string $markdown,
        public readonly string $html,
    ) {}
}
