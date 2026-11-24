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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->nullable();
            $table->double('amount');
            $table->string('bank_trans_id')->unique();
            $table->text('trans_description')->nullable();
            $table->string('status', 50)->default('SUCCESS');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};

