<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zoom_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->enum('account_type', ['pro', 'business']);

            // Di UI disebut "Kapasitas meeting".
            // Jumlah meeting yang boleh berjalan BERSAMAAN di akun ini.
            // Ini patokan status Penuh: jika meeting approved yang tumpang tindih
            // sudah sebanyak angka ini, slot tersebut Penuh. Minimal 1 (divalidasi di kode).
            $table->unsignedSmallInteger('allowed_login_users')->default(1);

            // Maksimal peserta per meeting. HANYA INFORMASI, tidak dipakai untuk memblokir booking.
            $table->unsignedInteger('participant_capacity');

            // Akun berlaku sampai tanggal ini (termasuk hari itu)
            $table->date('subscription_expires_at');
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Akun utama = satu-satunya akun yang tampil pertama kali di menu booking
            $table->boolean('is_default')->default(false);
            // Warna ikon akun di kalender
            $table->string('color', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoom_accounts');
    }
};