<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. agents
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->onDelete('set null');
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. suppliers
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. customers
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('agent_id')->nullable()->constrained('agents')->onDelete('set null');
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('passport_no', 50)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // 4. batches
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->string('batch_number', 30)->unique();
            $table->date('purchase_date')->nullable();
            $table->decimal('total_cost_foreign', 15, 2)->default(0);
            $table->decimal('total_paid_amount_foreign', 15, 2)->default(0);
            $table->decimal('exchange_rate', 15, 4);
            $table->string('status', 50)->default('pending'); // pending, partial, fully_paid, cost_allocated
            $table->smallInteger('cars_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('supplier_id');
            $table->index('status');
        });

        // 5. container_openers
        Schema::create('container_openers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('nif')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 6. service_providers
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('provider_type', 100);
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 7. cars
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->foreignId('container_opener_id')->nullable()->constrained('container_openers')->onDelete('set null');
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->string('finition', 255)->nullable();
            $table->integer('manufacture_year');
            $table->string('color', 50)->nullable();
            $table->string('vin', 50)->unique()->nullable();
            $table->decimal('foreign_purchase_price', 15, 2); // local purchase price according to design notes
            $table->decimal('sale_price', 15, 2);
            $table->string('tracking_number', 255)->nullable();
            $table->string('container_no', 50)->nullable();
            $table->date('shipping_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('status', 50);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 8. car_expenses
        Schema::create('car_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained('cars')->onDelete('cascade');
            $table->string('expense_type', 100); // Replacing raw enum with varchar for flexibility
            $table->decimal('foreign_amount', 15, 2);
            $table->decimal('local_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. orders
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('car_id')->constrained('cars')->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('agents')->onDelete('set null');
            $table->string('status', 50);
            $table->date('purchase_date')->nullable();
            $table->date('shipping_date')->nullable();
            $table->date('arrival_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 10. customer_payments (remittance_id added as nullable column first)
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('received_by', 20)->default('company'); // agent, company
            $table->foreignId('agent_id')->nullable()->constrained('agents')->onDelete('set null');
            $table->unsignedBigInteger('remittance_id')->nullable(); // will constrain later
            $table->string('attachment', 255)->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
            $table->index('payment_date');
            $table->index('agent_id');
            $table->index('remittance_id');
        });

        // 11. agent_transactions (payment_id and transaction_id added as nullable columns first)
        Schema::create('agent_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade');
            $table->string('direction', 10); // in, out
            $table->decimal('amount', 15, 2);
            $table->decimal('previous_balence', 18, 2);
            $table->decimal('current_balence', 18, 2);
            $table->unsignedBigInteger('payment_id')->nullable(); // will constrain later
            $table->unsignedBigInteger('transaction_id')->nullable(); // will constrain later
            $table->date('transaction_date');
            $table->string('attachment', 255)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            $table->index('agent_id');
            $table->index('transaction_date');
        });

        // 12. supplier_payments
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('suppliers')->onDelete('cascade');
            $table->decimal('amount_foreign', 15, 2);
            $table->decimal('exchange_rate', 15, 4);
            $table->decimal('amount_local', 15, 2);
            $table->string('attachment', 255)->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            $table->index('batch_id');
            $table->index('payment_date');
        });

        // 13. expenses
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained('cars')->onDelete('set null');
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->foreignId('service_provider_id')->nullable()->constrained('service_providers')->onDelete('set null');
            $table->string('expense_type', 100);
            $table->decimal('amount', 15, 2);
            $table->string('attachment', 255)->nullable();
            $table->date('expense_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index('order_id');
        });

        // 14. invoices
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('invoice_number', 30)->unique();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('status', 50);
            $table->timestamps();
        });

        // 15. documents
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained('cars')->onDelete('set null');
            $table->string('title', 150);
            $table->string('file_path', 255);
            $table->timestamp('created_at')->nullable();
        });

        // 16. treasury_transactions
        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 10); // in, out
            $table->decimal('amount', 15, 2);
            $table->decimal('previous_balence', 18, 2);
            $table->decimal('current_balence', 18, 2);
            $table->string('source_type', 30); // agent_remittance, supplier_payment, expense, customer_payment
            $table->unsignedBigInteger('source_id');
            $table->date('transaction_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index('transaction_date');
            $table->index(['source_type', 'source_id']);
        });

        // 17. settings
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 255);
            $table->timestamps();
        });

        // --- Circular Foreign Key Constraints Add ---
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->foreign('remittance_id')->references('id')->on('agent_transactions')->onDelete('set null');
        });

        Schema::table('agent_transactions', function (Blueprint $table) {
            $table->foreign('payment_id')->references('id')->on('customer_payments')->onDelete('set null');
            $table->foreign('transaction_id')->references('id')->on('treasury_transactions')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove circular keys first
        if (Schema::hasTable('agent_transactions')) {
            Schema::table('agent_transactions', function (Blueprint $table) {
                $table->dropForeign(['payment_id']);
                $table->dropForeign(['transaction_id']);
            });
        }
        if (Schema::hasTable('customer_payments')) {
            Schema::table('customer_payments', function (Blueprint $table) {
                $table->dropForeign(['remittance_id']);
            });
        }

        Schema::dropIfExists('settings');
        Schema::dropIfExists('treasury_transactions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('agent_transactions');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('car_expenses');
        Schema::dropIfExists('cars');
        Schema::dropIfExists('service_providers');
        Schema::dropIfExists('container_openers');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('agents');
    }
};
