<?php

namespace App\Http\Middleware;

use App\Support\Upload\UploadSizeLimit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class EnforceUploadSizeLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $maxBytes = UploadSizeLimit::maxBytes();

        foreach ($request->allFiles() as $files) {
            $this->assertFilesWithinLimit($this->flattenFiles($files), $maxBytes);
        }

        return $next($request);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function assertFilesWithinLimit(array $files, int $maxBytes): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            if ($file->getSize() > $maxBytes) {
                abort(422, __('validation.max.file', [
                    'attribute' => __('validation.attributes.file'),
                    'max' => UploadSizeLimit::maxKilobytes(),
                ]));
            }
        }
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function flattenFiles(mixed $files): array
    {
        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        $flattened = [];

        foreach ($files as $file) {
            foreach ($this->flattenFiles($file) as $uploaded) {
                $flattened[] = $uploaded;
            }
        }

        return $flattened;
    }
}
