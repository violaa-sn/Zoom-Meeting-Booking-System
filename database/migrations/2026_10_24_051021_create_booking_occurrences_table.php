<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_occurrences', function (Blueprint $table) {
            $table->id();
            // restrict: data kejadian nyata tidak boleh hilang diam-diam
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('zoom_account_id')->constrained('zoom_accounts')->restrictOnDelete();

            // Satu baris = satu tanggal nyata hasil dari pola booking.
            // Hanya dibuat saat Admin approve. Booking pending tidak punya baris di sini.
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->enum('status', ['approved', 'cancelled'])->default('approved');
            $table->timestamps();

            // Mencegah baris ganda jika approve terjadi dua kali
            $table->unique(['booking_id', 'starts_at']);
            // Untuk cek penuh/bentrok dan query kalender per akun per minggu
            $table->index(['zoom_account_id', 'status', 'starts_at', 'ends_at'], 'occ_conflict_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_occurrences');
    }
};