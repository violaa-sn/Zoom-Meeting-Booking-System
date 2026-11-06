<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Setiap pengguna (termasuk admin) WAJIB punya departemen. Aturan wajibnya dijaga di aplikasi
            // restrictOnDelete: departemen yang masih punya user TIDAK bisa dihapus
            $table->foreignId('department_id')->nullable()->after('id')
                ->constrained('departments')->restrictOnDelete();

            // Login memakai username. "name" bawaan Laravel dipakai sebagai nama lengkap
            $table->string('username', 50)->unique()->after('name');

            $table->enum('role', ['admin', 'user'])->default('user')->after('password');
            // Toggle aktif/nonaktif. User nonaktif tidak bisa login, tapi riwayatnya tetap ada
            $table->enum('status', ['active', 'inactive'])->default('active')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // dropConstrainedForeignId menghapus FK dulu, baru kolomnya
            $table->dropConstrainedForeignId('department_id');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role', 'status']);
        });
    }
};