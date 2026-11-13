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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('car_id');
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->json('options')->nullable();
            $table->double('total_amount');
            $table->string('status', 50)->default('PENDING_PAYMENT');
            $table->string('transfer_code', 100)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('maTaiKhoan')
                ->on('tai_khoans')
                ->onDelete('cascade');

            $table->foreign('car_id')
                ->references('maXe')
                ->on('xes')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

