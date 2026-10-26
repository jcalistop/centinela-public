<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Configuration;

use App\Support\Configuration\AdminBackgroundImage;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminBackgroundImageTest extends TestCase
{
    public function test_cached_url_uses_remember_forever(): void
    {
        Cache::shouldReceive('rememberForever')
            ->once()
            ->with('centinela.admin.bg_image.url', \Mockery::type('Closure'))
            ->andReturn('/storage/settings/bg.jpg');

        $this->assertSame('/storage/settings/bg.jpg', AdminBackgroundImage::cachedUrl());
    }

    public function test_invalidate_forgets_cache_key(): void
    {
        Cache::put('centinela.admin.bg_image.url', '/storage/settings/bg.jpg');

        AdminBackgroundImage::invalidate();

        $this->assertFalse(Cache::has('centinela.admin.bg_image.url'));
    }
}
