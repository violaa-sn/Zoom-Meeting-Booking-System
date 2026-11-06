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

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('zoom_account_id')->nullable()->constrained('zoom_accounts')->restrictOnDelete();
            // Tipe akun yang diminta user (pro / business). Admin hanya diberi pilihan akun bertipe ini
            $table->enum('account_type', ['pro', 'business']);
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->enum('purpose', ['rapat', 'presentasi', 'pelatihan', 'wawancara', 'diskusi', 'lainnya']);
            $table->string('purpose_other')->nullable();
            $table->enum('participant_count', ['100', '300', '500', '500+']);

            // Tanggal yang dipilih user (kejadian pertama)
            $table->date('starts_on');
            // Jam mulai & selesai, menit bebas (mis. 09:12 sampai 10:24). Kalender saja yang dipecah per 30 menit.
            // Jika end_time <= start_time berarti meeting melewati tengah malam
            $table->time('start_time');
            $table->time('end_time');

            // Pengulangan. none = tidak berulang.
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
