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
        Schema::create('khach_hangs', function (Blueprint $table) {
            $table->id('maKhachHang');
            $table->unsignedBigInteger('maTaiKhoan')->unique();
            $table->string('hoTen');
            $table->date('ngaySinh')->nullable();
            $table->string('soDienThoai')->nullable();
            $table->string('email')->nullable();
            $table->text('diaChi')->nullable();
            $table->timestamps();

            $table->foreign('maTaiKhoan')
                ->references('maTaiKhoan')
                ->on('tai_khoans')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('khach_hangs');
    }
};
