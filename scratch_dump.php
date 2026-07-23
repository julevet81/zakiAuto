<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => database_path('database.sqlite')]);

try {
    print_r(App\Models\CustomerDocument::all()->toArray());
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
