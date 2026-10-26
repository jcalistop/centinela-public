<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

Cache::forget('indicator_type_routes');
DB::table('indicator_types')->delete();

$rows = json_decode(
    (string) file_get_contents(dirname(__DIR__).'/fixtures/usable-catalog/indicator_types.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);

DB::table('indicator_types')->insert($rows);

foreach (DB::table('indicator_types')->orderBy('id')->get(['id', 'route', 'estado']) as $row) {
    echo "{$row->id}\t{$row->route}\testado={$row->estado}\n";
}

echo "OK — reinicia PHP / recarga /admin (caché indicator_type_routes olvidada).\n";
