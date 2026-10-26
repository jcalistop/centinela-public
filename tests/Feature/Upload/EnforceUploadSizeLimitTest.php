<?php

declare(strict_types=1);

namespace Tests\Feature\Upload;

use App\Http\Middleware\EnforceUploadSizeLimit;
use App\Support\Upload\UploadSizeLimit;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EnforceUploadSizeLimitTest extends TestCase
{
    public function test_allows_file_within_platform_ceiling(): void
    {
        $middleware = new EnforceUploadSizeLimit;
        $file = UploadedFile::fake()->create('documento.pdf', UploadSizeLimit::maxKilobytes() - 1);

        $request = Request::create('/admin/upload', 'POST', [], [], ['file' => $file]);

        $response = $middleware->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function test_rejects_file_above_platform_ceiling(): void
    {
        $middleware = new EnforceUploadSizeLimit;
        $file = UploadedFile::fake()->create('grande.pdf', UploadSizeLimit::maxKilobytes() + 1);

        $request = Request::create('/admin/upload', 'POST', [], [], ['file' => $file]);

        $this->expectException(HttpException::class);

        try {
            $middleware->handle($request, fn () => response('ok'));
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());

            throw $exception;
        }
    }
}
