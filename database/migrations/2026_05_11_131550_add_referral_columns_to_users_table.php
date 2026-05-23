// database/migrations/xxxx_add_referral_columns_to_users_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('referral_points')->default(0)->after('password');
            $table->foreignId('referred_by')->nullable()->after('referral_points');
            $table->string('referral_code_used')->nullable()->after('referred_by');
            
            // Foreign key constraint
            $table->foreign('referred_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn(['referral_points', 'referred_by', 'referral_code_used']);
        });
    }
};