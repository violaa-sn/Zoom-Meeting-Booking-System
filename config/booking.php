<?php

return [
    // Jam yang tampil di kalender dan boleh dibooking: 06:00 sampai 24:00 (tengah malam).
    // Angka ini juga dipakai CalendarService dan RecommendBookingRequest.
    'open_minute' => 6 * 60,
    'close_minute' => 24 * 60,

    // Pesan popup saat user mencari akun di luar jam operasional.
    // Ganti teks ini sesukamu, mis. "Waktunya tidur hey".
    'out_of_hours_message' => 'Hanya bisa 6 pagi - 12 malam',

    // Rentang maksimal yang boleh ditampilkan di kalender (hari).
    'max_range_days' => null, 

    // Pengulangan TIDAK dibatasi tahun. Yang dibatasi hanya jumlah tanggal per pengajuan,
    // supaya satu pengajuan tidak membuat puluhan ribu baris dan server jadi lambat.
    // 3000 tanggal = sekitar 8 tahun kalau diulang setiap hari, atau 57 tahun kalau mingguan.
    'max_occurrences' => 3000,
];
