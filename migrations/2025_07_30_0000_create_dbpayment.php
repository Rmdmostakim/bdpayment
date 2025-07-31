<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bdpayments', function (Blueprint $table) {
            $table->id();


            // User ID who made the payment
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->index();
            // Invoice ID or other related model
            $table->string('invoice')->unique()->index();
            // Payment method used
            $table->enum('mode', ['bkash', 'nagad', 'rocket', 'upay', 'sslcommerz', 'bank_transfer'])->index();


            // Unique gateway transaction ID
            $table->string('transaction_id')->nullable()->unique();


            // Payment amount and currency
            $table->decimal('amount', 12, 2)->index();
            $table->string('currency', 10)->default('BDT');


            // Payment status
            $table->enum('status', [
                'initiated',   // User started but didn't proceed to gateway
                'pending',     // Payment in progress or waiting confirmation
                'completed',   // Payment successfully processed
                'failed',      // Payment failed due to error
                'cancelled'    // User or system cancelled the payment
            ])->default('initiated')->index();


            // Optional note (e.g., manual note or reference)
            $table->text('note')->nullable();


            // Optional sender info for mobile banking
            $table->string('sender_name')->nullable()->index();
            $table->string('sender_phone')->nullable()->index();
            $table->string('receiver_account')->nullable();


            // SSLCommerz-specific metadata
            $table->string('bank_transaction_id')->nullable()->index();
            $table->string('card_type')->nullable();
            $table->string('card_no')->nullable();


            // Bank transfer details
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('branch_name')->nullable();


            // Polymorphic relationship to payable model (e.g., Order, Invoice, etc.)
            $table->nullableMorphs('payable'); // adds: payable_id + payable_type (indexed)


            // When payment was successfully completed
            $table->timestamp('paid_at')->nullable();


            $table->timestamps(); // created_at and updated_at
            $table->softDeletes(); // deleted_at for soft deletes
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bdpayments');
    }
};
