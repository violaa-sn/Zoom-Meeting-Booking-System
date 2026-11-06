<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penanda: peminjam sudah diberi tahu bahwa pengajuan pending-nya lewat tanggal (supaya tidak dikirim berulang)
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('expired_notified_at')->nullable();
        });

        // Jenis notifikasi baru: pengajuan lewat tanggal sebelum sempat diproses admin
        Schema::table('booking_notifications', function (Blueprint $table) {
            $table->enum('type', ['approved', 'rejected', 'cancelled', 'expired'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('booking_notifications', function (Blueprint $table) {
            $table->enum('type', ['approved', 'rejected', 'cancelled'])->change();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('expired_notified_at');
        });
    }
};
