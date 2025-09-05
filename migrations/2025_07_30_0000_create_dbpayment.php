<?php

/**
 * Migration: Create bdpayments Table
 *
 * This migration creates the bdpayments table for storing payment transactions
 * for all supported gateways (Bkash, Nagad, SSLCommerz, etc).
 * Includes polymorphic relation, gateway metadata, and indexing for performance.
 *
 * @package RmdMostakim\BdPayment\Migrations
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Creates the bdpayments table with all necessary columns and indexes.
     */
    public function up(): void
    {
        Schema::create('bdpayments', function (Blueprint $table) {
            $table->id();

            // Product related to payment (optional)
            $table->integer('product_id')->nullable()->index();

            // User ID who made the payment (optional, foreign key)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade')->index();

            // Unique invoice ID for the payment
            $table->string('invoice')->unique()->index();

            // Payment gateway/method used
            $table->enum('mode', [
                'bkash', 'nagad', 'rocket', 'upay', 'sslcommerz', 'bank_transfer'
            ])->index();

            // Unique transaction ID from gateway
            $table->string('transaction_id')->nullable()->unique();

            // Payment amount and currency
            $table->decimal('amount', 12, 2)->index();
            $table->string('currency', 10)->default('BDT')->index();

            // Payment status
            $table->enum('status', [
                'initiated',   // User started but didn't proceed to gateway
                'pending',     // Payment in progress or waiting confirmation
                'completed',   // Payment successfully processed
                'failed',      // Payment failed due to error
                'cancelled'    // User or system cancelled the payment
            ])->default('initiated')->index();

            // Optional note (manual note or reference)
            $table->text('note')->nullable();

            // Sender info for mobile banking (optional)
            $table->string('sender_name')->nullable()->index();
            $table->string('sender_phone')->nullable()->index();
            $table->string('receiver_account')->nullable();

            // SSLCommerz-specific metadata (optional)
            $table->string('bank_transaction_id')->nullable()->index();
            $table->string('card_type')->nullable()->index();
            $table->string('card_no')->nullable()->index();

            // Bank transfer details (optional)
            $table->string('bank_name')->nullable()->index();
            $table->string('account_number')->nullable()->index();
            $table->string('branch_name')->nullable()->index();

            // Polymorphic relationship to payable model (Order, Invoice, etc.)
            $table->nullableMorphs('payable'); // adds: payable_id + payable_type (indexed)

            // When payment was successfully completed
            $table->timestamp('paid_at')->nullable()->index();

            $table->timestamps(); // created_at and updated_at
            $table->softDeletes(); // deleted_at for soft deletes
        });
    }

    /**
     * Reverse the migrations.
     * Drops the bdpayments table.
     */
    public function down(): void
    {
        Schema::dropIfExists('bdpayments');
    }
};
