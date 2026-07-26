<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Use default MySQL connection
try {
    echo "=== MySQL Table Row Counts ===\n";
    $tables = DB::select('SHOW TABLES');
    foreach ($tables as $table) {
        $tableName = $table->Tables_in_zekrimotors;
        $count = DB::table($tableName)->count();
        if ($count > 0) {
            echo "$tableName: $count\n";
        }
    }
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}

try {
    echo "\n=== SQLite database.sqlite Table Row Counts ===\n";
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => database_path('database.sqlite')]);
    DB::purge('sqlite');
    DB::reconnect('sqlite');
    $tables = DB::connection('sqlite')->select("SELECT name FROM sqlite_master WHERE type='table'");
    foreach ($tables as $table) {
        $tableName = $table->name;
        try {
            $count = DB::connection('sqlite')->table($tableName)->count();
            if ($count > 0) {
                echo "$tableName: $count\n";
            }
        } catch (\Exception $e) {
            // Table might not exist or be empty
        }
    }
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}

try {
    echo "\n=== SQLite Zekrimotors Table Row Counts ===\n";
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => base_path('Zekrimotors')]);
    DB::purge('sqlite');
    DB::reconnect('sqlite');
    $tables = DB::connection('sqlite')->select("SELECT name FROM sqlite_master WHERE type='table'");
    foreach ($tables as $table) {
        $tableName = $table->name;
        try {
            $count = DB::connection('sqlite')->table($tableName)->count();
            if ($count > 0) {
                echo "$tableName: $count\n";
            }
        } catch (\Exception $e) {
            // Table might not exist or be empty
        }
    }
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
