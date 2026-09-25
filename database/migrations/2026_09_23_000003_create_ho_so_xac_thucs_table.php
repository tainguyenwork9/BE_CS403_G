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
        Schema::create('ho_so_xac_thucs', function (Blueprint $table) {
            $table->id('maHoSo');
            $table->unsignedBigInteger('maKhachHang');
            $table->string('soCCCD')->nullable();
            $table->string('anhCCCDMatTruoc')->nullable();
            $table->string('anhCCCDMatSau')->nullable();
            $table->string('soGPLX')->nullable();
            $table->string('anhGPLX')->nullable();
            $table->string('trangThai')->default('cho_duyet');
            $table->timestamp('ngayGui')->nullable();
            $table->timestamp('ngayXacThuc')->nullable();
            $table->timestamps();

            $table->foreign('maKhachHang')
                ->references('maKhachHang')
                ->on('khach_hangs')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ho_so_xac_thucs');
    }
};
