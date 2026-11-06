<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengguna yang dinonaktifkan admin langsung dikeluarkan pada request berikutnya,
 * tanpa menunggu sesinya habis. Status dibaca dari database di setiap request,
 * jadi perubahan peran atau status oleh admin juga langsung berlaku.
 */
class EnsureUserIsActive
{
    public const MESSAGE = 'Akunmu dinonaktifkan oleh admin. Hubungi admin untuk mengaktifkannya kembali.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Permintaan fetch (rekomendasi akun, daftar tanggal): 401, JS yang mengarahkan ke login
            if ($request->expectsJson()) {
                return response()->json(['message' => self::MESSAGE], 401);
            }

            return redirect()->route('login')->with('error', self::MESSAGE);
        }

        return $next($request);
    }
}
