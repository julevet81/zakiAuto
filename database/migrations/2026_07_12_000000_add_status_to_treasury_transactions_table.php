<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            // Default 'approved' so every existing row, and every row
            // created by the app's current flows (postTreasuryMovement,
            // remit, postTreasuryReversal...), keeps behaving exactly as
            // before — only the new "transfer to general treasury" action
            // explicitly creates 'pending' rows.
            $table->string('status')->default('approved')->after('current_balence');

            $table->foreignId('approved_by')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('approved_by');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at']);
        });
    }
};
