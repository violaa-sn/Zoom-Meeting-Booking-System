<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_notifications', function (Blueprint $table) {
            $table->id();
            // Notifikasi bersifat sementara, ikut terhapus bersama usernya
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Jika booking terhapus, pesan tetap ada (booking_id jadi null)
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();

            $table->enum('type', ['approved', 'rejected', 'cancelled']);
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_notifications');
    }
};