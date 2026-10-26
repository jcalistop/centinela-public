<?php

declare(strict_types=1);

$path = dirname(__DIR__).'/public/css/centinela-compass-ui.css';
$content = file_get_contents($path);

$content = preg_replace_callback(
    '/\.voyager \.cen-compass-ui, \.voyager \.admin-dashboard-compass(--modal)? ([^{]+)\{/',
    static function (array $matches): string {
        $suffix = $matches[1] ?? '';
        $selector = trim($matches[2]);

        return ".voyager .cen-compass-ui {$selector}, .voyager .admin-dashboard-compass{$suffix} {$selector} {";
    },
    $content
) ?? $content;

$content = preg_replace(
    "/\.voyager \.cen-compass-ui,\n\.voyager \.admin-dashboard-compass \{\n    margin-top: 1\.5rem;\n    margin-bottom: 0\.5rem;\n\}\n\n/",
    '',
    $content
);

$content = preg_replace(
    '/^\.voyager \.cen-compass-ui,\n\.voyager \.admin-dashboard-compass \{/m',
    ".voyager .admin-dashboard-compass {\n    margin-top: 1.5rem;\n    margin-bottom: 0.5rem;\n}\n\n.voyager .cen-compass-ui,\n.voyager .admin-dashboard-compass {",
    $content,
    1
);

file_put_contents($path, $content);

echo "Fixed\n";
