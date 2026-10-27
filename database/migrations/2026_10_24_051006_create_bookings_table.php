<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete di semua FK: riwayat booking tidak boleh hilang
            // karena user, akun Zoom, atau admin dihapus
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // Wajib diisi: user sudah memilih akun dari rekomendasi sebelum mengajukan
            $table->foreignId('zoom_account_id')->constrained('zoom_accounts')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->enum('purpose', ['rapat', 'presentasi', 'pelatihan', 'wawancara', 'diskusi', 'lainnya']);
            // Diisi hanya jika purpose = lainnya
            $table->string('purpose_other')->nullable();
            // Wajib diisi di form, tapi hanya informasi. Tidak dipakai untuk memblokir booking
            $table->unsignedInteger('participant_count');

            // Tanggal yang dipilih user (kejadian pertama)
            $table->date('starts_on');
            // Jam mulai & selesai. Jika end_time <= start_time berarti meeting melewati tengah malam
            $table->time('start_time');
            $table->time('end_time');

            // Pengulangan. none = tidak berulang.
            // Tidak ada "never": batas akhir wajib On atau After
            $table->enum('recurrence_frequency', ['none', 'day', 'week', 'month', 'year'])->default('none');
            // Angka "repeat every" (contoh: setiap 2 minggu)
            $table->unsignedSmallInteger('repeat_interval')->default(1);
            // Null jika tidak berulang
            $table->enum('recurrence_end_type', ['on', 'after'])->nullable();
            $table->date('recurrence_ends_on')->nullable();      // dipakai jika end_type = on
            $table->unsignedSmallInteger('recurrence_ends_after')->nullable(); // dipakai jika end_type = after

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['zoom_account_id', 'status']);
            $table->index(['status', 'created_at']); // daftar persetujuan admin
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};