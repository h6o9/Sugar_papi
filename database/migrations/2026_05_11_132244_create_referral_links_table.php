// database/migrations/xxxx_create_referral_links_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('referral_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('referral_code')->unique();
            $table->string('referral_url')->unique();
            $table->integer('total_clicks')->default(0);
            $table->integer('successful_registrations')->default(0);
            $table->timestamps();
            
            // Index for faster lookup
            $table->index('referral_code');
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('referral_links');
    }
};