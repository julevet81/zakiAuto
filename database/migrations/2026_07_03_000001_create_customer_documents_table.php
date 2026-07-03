<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer profile documents — multiple files per customer
     * (passport copy, national ID scan, contract, power of attorney, etc.)
     * Separate from car-level documents (which live in the `documents` table
     * linked to `cars`). These belong to the customer's profile itself.
     */
    public function up(): void
    {
        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->onDelete('cascade');
            $table->string('title', 150);
            $table->string('file_path', 255);
            $table->string('file_type', 50)->nullable(); // mime type
            $table->string('file_size')->nullable();     // human-readable e.g. "2.3 MB"
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->onDelete('restrict');
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_documents');
    }
};
