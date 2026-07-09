<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->string('batch_number', 30)->nullable()->unique()->after('id');
        });

        DB::table('batches')
            ->select('id')
            ->orderBy('id')
            ->cursor()
            ->each(function ($batch): void {
                DB::table('batches')
                    ->where('id', $batch->id)
                    ->update([
                        'batch_number' => 'BATCH-'.str_pad((string) $batch->id, 6, '0', STR_PAD_LEFT),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropUnique(['batch_number']);
            $table->dropColumn('batch_number');
        });
    }
};
