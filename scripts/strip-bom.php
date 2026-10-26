<?php

$path = $argv[1] ?? '';
if ($path === '' || ! is_file($path)) {
    fwrite(STDERR, "Usage: php strip-bom.php <file>\n");
    exit(1);
}

$contents = file_get_contents($path);
if (str_starts_with($contents, "\xEF\xBB\xBF")) {
    file_put_contents($path, substr($contents, 3));
    echo "BOM removed from {$path}\n";
    exit(0);
}

echo "No BOM in {$path}\n";
