<?php

namespace App\Support\UserManual;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class UserManualIndex
{
    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $path = $this->path();

        if (! File::exists($path)) {
            return [];
        }

        return Yaml::parseFile($path) ?? [];
    }

    public function path(): string
    {
        return resource_path('user-manual/index.yaml');
    }

    public function fallbackId(array $index): string
    {
        $fallback = data_get($index, 'defaults.fallback_id', 'general');

        return is_string($fallback) && $fallback !== '' ? $fallback : 'general';
    }

    public function resolveIdForContext(?string $routeName, ?string $path): string
    {
        $index = $this->load();
        $fallback = $this->fallbackId($index);
        $rules = Arr::wrap($index['rules'] ?? []);

        foreach ($rules as $rule) {
            $match = $rule['match'] ?? null;
            $target = $rule['target'] ?? null;

            if (! is_array($match) || ! is_array($target)) {
                continue;
            }

            $type = $match['type'] ?? null;
            $value = $match['value'] ?? null;
            $id = $target['id'] ?? null;

            if (! is_string($id) || $id === '') {
                continue;
            }

            if (! is_string($type) || ! is_string($value) || $value === '') {
                continue;
            }

            if ($type === 'route_exact' && $routeName && $routeName === $value) {
                return $id;
            }

            if ($type === 'route_prefix' && $routeName && str_starts_with($routeName, $value)) {
                return $id;
            }

            if ($type === 'route_regex' && $routeName && @preg_match("/{$value}/", '') !== false) {
                if (@preg_match("/{$value}/", $routeName) === 1) {
                    return $id;
                }
            }

            if ($type === 'path_prefix' && $path && str_starts_with($path, $value)) {
                return $id;
            }
        }

        return $fallback;
    }
}
