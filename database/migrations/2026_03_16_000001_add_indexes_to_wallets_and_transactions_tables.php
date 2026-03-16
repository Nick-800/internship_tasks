<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->index('user_id', 'wallets_user_id_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(
                ['destination_wallet_id', 'created_at'],
                'transactions_destination_wallet_created_at_index'
            );

            $table->index(
                ['source_wallet_id', 'created_at'],
                'transactions_source_wallet_created_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_source_wallet_created_at_index');
            $table->dropIndex('transactions_destination_wallet_created_at_index');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropIndex('wallets_user_id_index');
        });
    }
};
