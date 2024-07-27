<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletsSolanaTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets_solana_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('address_id')->constrained('wallets_solana_address')->onDelete('cascade');
            $table->string('type');
            $table->string('to_address')->nullable();
            $table->string('sol_transaction_id')->nullable();
            $table->decimal('amount', 20, 8);
            $table->string('status');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets_solana_transactions');
    }
};
