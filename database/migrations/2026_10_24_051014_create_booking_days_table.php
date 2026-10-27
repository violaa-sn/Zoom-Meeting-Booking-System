<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_days', function (Blueprint $table) {
            $table->id();
            // cascade aman: ini hanya bagian dari pola booking, bukan riwayat
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            // "Repeats on". 0 = Minggu, 1 = Senin, ... 6 = Sabtu (urutan S M T W T F S)
            $table->unsignedTinyInteger('day_of_week');
            $table->timestamps();

            // Hari yang sama tidak boleh dipilih dua kali
            $table->unique(['booking_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_days');
    }
};