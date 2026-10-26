<?php

namespace App\Support\UserManual;

use App\Support\UserManual\Dto\UserManualPage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UserManualRepository
{
    public function __construct(
        private readonly UserManualIndex $index,
        private readonly UserManualRenderer $renderer,
    ) {}

    public function pagesPath(): string
    {
        return resource_path('user-manual/pages');
    }

    public function resolveIdForContext(?string $routeName, ?string $path): string
    {
        return $this->index->resolveIdForContext($routeName, $path);
    }

    public function find(string $id): ?UserManualPage
    {
        $id = Str::slug($id);
        $file = $this->pagesPath().DIRECTORY_SEPARATOR.$id.'.md';

        if (! File::exists($file)) {
            return null;
        }

        $mtime = File::lastModified($file);
        $cacheKey = "user-manual:page:{$id}:{$mtime}";

        return Cache::rememberForever($cacheKey, function () use ($id, $file) {
            $markdown = File::get($file);
            $rendered = $this->renderer->render($markdown);

            $front = $rendered['frontMatter'] ?? [];
            $title = is_string($front['title'] ?? null) ? (string) $front['title'] : $id;

            return new UserManualPage(
                id: $id,
                title: $title,
                frontMatter: is_array($front) ? $front : [],
                markdown: $markdown,
                html: (string) ($rendered['html'] ?? ''),
            );
        });
    }

    /**
     * @return array<int, array{id: string, title: string, status: string|null, updated_at: string|null}>
     */
    public function list(): array
    {
        $dir = $this->pagesPath();
        if (! File::isDirectory($dir)) {
            return [];
        }

        $out = [];
        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $id = Str::slug($file->getFilenameWithoutExtension());
            $page = $this->find($id);
            if (! $page) {
                continue;
            }

            $out[] = [
                'id' => $page->id,
                'title' => $page->title,
                'status' => is_string($page->frontMatter['status'] ?? null) ? (string) $page->frontMatter['status'] : null,
                'updated_at' => is_string($page->frontMatter['updated_at'] ?? null) ? (string) $page->frontMatter['updated_at'] : null,
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['title'], $b['title']));

        return $out;
    }
}
