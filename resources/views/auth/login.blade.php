<x-layouts.guest title="Masuk">
    <div class="row g-0 min-vh-100">
        <aside class="col-lg-5 login-side d-flex flex-column justify-content-between gap-4 p-4 p-lg-5">
            <div>
                @include('partials.logo')
                <h1 class="mt-3 mb-2">Zoom Booking Account System</h1>
                <p class="mb-0">Pinjam akun Zoom untuk rapat, kelas, dll. Lihat jadwal yang kosong, ajukan,
                    lalu tunggu persetujuan admin.</p>
            </div>
            <div class="d-none d-lg-flex flex-column gap-2 align-items-start" aria-label="Arti warna di kalender">
                <span class="chip chip--available"><i></i>Tersedia</span>
                <span class="chip chip--pending"><i></i>Menunggu persetujuan</span>
                <span class="chip chip--approved"><i></i>Disetujui</span>
                <span class="chip chip--full"><i></i>Penuh atau tidak tersedia</span>
            </div>
        </aside>
        <main class="col-lg-7 d-flex align-items-center justify-content-center p-4">
            <form class="w-100 d-grid gap-3" style="max-width:380px" method="POST" action="{{ route('login.store') }}">
                @csrf
                <div>
                    <h2 class="h4 fw-bold mb-1">Masuk</h2>
                    <p class="text-secondary mb-0">Gunakan username dan password dari admin.</p>
                </div>
                <x-input label="Username" name="username" autocomplete="username" autofocus required />
                <x-input label="Password" name="password" type="password" autocomplete="current-password" required />
                <div class="form-check"><input class="form-check-input" type="checkbox" id="remember" name="remember"
                        value="1"><label class="form-check-label" for="remember">Ingat saya di perangkat
                        ini</label></div>
                <x-button type="submit" size="lg">Masuk</x-button>
                <p class="form-text mb-0">Lupa password? Hubungi admin untuk mengatur ulang.</p>
            </form>
        </main>
    </div>
</x-layouts.guest>
