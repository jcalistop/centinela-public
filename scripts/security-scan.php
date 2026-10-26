<?php

/**
 * Simple security scan to detect patterns that shouldn't exist in app code.
 * Intended to run in CI. Exits with non-zero code when dangerous patterns are found.
 */
$root = __DIR__.DIRECTORY_SEPARATOR.'..';

$excludeDirs = [
    $root.DIRECTORY_SEPARATOR.'vendor',
    $root.DIRECTORY_SEPARATOR.'storage',
    $root.DIRECTORY_SEPARATOR.'node_modules',
    $root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build',
    $root.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'vendor',
    $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'releases',
    $root.DIRECTORY_SEPARATOR.'.git',
];

$dangerousPatterns = [
    // System command execution
    '/\b(shell_exec|(?<!\.)exec|system|passthru|proc_open|popen)\s*\(/i' => 'System command execution',
    // Symfony Process arbitrary command usage
    '/new\s+Process\s*\(/i' => 'Symfony Process execution',
    // Artisan potentially destructive commands
    '/Artisan::call\s*\(\s*[\"\'](?:db:wipe|migrate:fresh|migrate:refresh)[\"\']/i' => 'Destructive Artisan command',
    // Raw DROP/TRUNCATE in queries
    '/DB::statement\s*\(\s*[\"\']\s*(?:(?:DROP|TRUNCATE)\s+(?:TABLE|DATABASE))/i' => 'Raw DROP/TRUNCATE statement',
];

// Allow list: files where drops are expected (migrations/stubs) or system commands are necessary (backup)
$allowIfPathContains = [
    DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'stubs'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Commands'.DIRECTORY_SEPARATOR.'DatabaseBackup.php',
    DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Commands'.DIRECTORY_SEPARATOR.'GitPullCommand.php',
    DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'Deploy'.DIRECTORY_SEPARATOR.'DeployOrchestrator.php',
    DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'Deploy'.DIRECTORY_SEPARATOR.'DeployedVersionResolver.php',
    DIRECTORY_SEPARATOR.'scripts'.DIRECTORY_SEPARATOR.'sdd-validate.php',
];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

$issues = [];

foreach ($rii as $file) {
    if ($file->isDir()) {
        continue;
    }

    $path = $file->getPathname();

    // Skip excluded directories
    foreach ($excludeDirs as $excluded) {
        if (str_starts_with($path, $excluded)) {
            continue 2;
        }
    }

    // Only check code/text files
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (! in_array($ext, ['php', 'blade.php', 'js', 'ts', 'md', 'env', 'yml', 'yaml'])) {
        continue;
    }

    $content = @file_get_contents($path);
    if ($content === false) {
        continue;
    }

    foreach ($dangerousPatterns as $regex => $desc) {
        if (preg_match($regex, $content)) {
            // Skip if path is allow-listed for this type (e.g., migrations/stubs for drop)
            $allow = false;
            foreach ($allowIfPathContains as $allowedPathPart) {
                if (str_contains($path, $allowedPathPart)) {
                    $allow = true;
                    break;
                }
            }

            // Symfony Process and System commands only allowed in explicitly whitelisted files
            // (e.g., DatabaseBackup for shell_exec, GitPullCommand for Process)
            if (stripos($desc, 'Process') !== false && ! $allow) {
                $allow = false;
            }
            if (stripos($desc, 'System command') !== false && ! $allow) {
                $allow = false;
            }

            if (! $allow) {
                $issues[] = [
                    'file' => $path,
                    'pattern' => $desc,
                ];
            }
        }
    }
}

if (! empty($issues)) {
    fwrite(STDERR, "Security scan found potential issues:\n");
    foreach ($issues as $i => $issue) {
        fwrite(STDERR, sprintf("%3d) %s => %s\n", $i + 1, $issue['pattern'], $issue['file']));
    }
    exit(1);
}

echo 'Security scan passed: no dangerous patterns found.'.PHP_EOL;
