<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('department')
            ->withCount('bookings')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes(trim($request->query('search')), '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('username', 'like', $term));
            })
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->query('department_id')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('id') // pengguna yang baru ditambahkan tampil paling atas
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()->route('users.index')->with('success', 'Pengguna ditambahkan.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Password kosong = tidak diganti
        if (empty($data['password'])) {
            unset($data['password']);
        }

        // Admin tidak boleh mengunci dirinya sendiri
        if ($user->is($request->user()) && ($data['role'] !== User::ROLE_ADMIN || $data['status'] !== User::STATUS_ACTIVE)) {
            return back()->withInput()->with('error', 'Kamu tidak bisa menurunkan peran atau menonaktifkan akunmu sendiri.');
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'Perubahan pengguna disimpan.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Kamu tidak bisa menghapus akunmu sendiri.');
        }

        // Riwayat booking dilindungi restrictOnDelete; beri pesan yang jelas sebelum database menolak
        if ($user->bookings()->exists() || $user->approvedBookings()->exists()) {
            return back()->with('error', "{$user->name} punya riwayat booking, jadi tidak bisa dihapus. Ubah statusnya menjadi Nonaktif.");
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Pengguna dihapus.');
    }
}
