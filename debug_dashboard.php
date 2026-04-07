<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $describe = Illuminate\Support\Facades\DB::select('DESCRIBE exams');
    print_r($describe);
    echo "tables:\n";
    $tables = Illuminate\Support\Facades\DB::select("SHOW TABLES LIKE '%subject%' ");
    print_r($tables);
    
    echo "Testing join query:\n";
    $examsBySubject = Illuminate\Support\Facades\DB::select("
        SELECT subjects.name as subject, COUNT(*) as count
        FROM exams
        JOIN subjects ON exams.subject_id = subjects.id
        GROUP BY subjects.name
    ");
    print_r($examsBySubject);
} catch (Throwable $e) {
    echo "EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
