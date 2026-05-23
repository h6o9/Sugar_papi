<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'stripe_session_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('stripe_session_id')->nullable()->unique()->after('payment');
            });
        }

        if (!Schema::hasColumn('orders', 'gateway_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('gateway_fee', 10, 2)->default(0)->after('total_amount');
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'stripe_session_id')) {
                $table->dropColumn('stripe_session_id');
            }
            if (Schema::hasColumn('orders', 'gateway_fee')) {
                $table->dropColumn('gateway_fee');
            }
        });
    }
};
