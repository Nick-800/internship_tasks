<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // source_wallet_id is nullable: deposits have no source (they come from "outside")
            $table->foreignId('source_wallet_id')
                ->nullable()
                ->constrained('wallets')
                ->nullOnDelete();
            $table->foreignId('destination_wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            // type: 'deposit' | 'transfer'
            $table->enum('type', ['deposit', 'transfer']);
            // status: 'completed' | 'failed' — once 'completed', the Observer blocks edits
            $table->enum('status', ['completed', 'failed'])->default('completed');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
