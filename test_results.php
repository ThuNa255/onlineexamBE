<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $results = Illuminate\Support\Facades\DB::table('results')->select('completed_at')->get();
    foreach ($results as $r) {
        echo 'Completed_at: ' . $r->completed_at . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . PHP_EOL;
}