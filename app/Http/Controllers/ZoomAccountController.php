<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZoomAccountRequest;
use App\Models\ZoomAccount;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoomAccountController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'account_type', 'allowed_login_users', 'participant_capacity']);

        return view('zoom-accounts.index', [
            'accounts' => ZoomAccount::query()->filter($filters)->listOrder()->get(),
            // Pilihan dropdown diambil dari nilai yang benar-benar dipakai akun
            'meetingCapacities' => ZoomAccount::query()->distinct()->orderBy('allowed_login_users')->pluck('allowed_login_users')->all(),
            'participantCapacities' => ZoomAccount::query()->distinct()->orderBy('participant_capacity')->pluck('participant_capacity')->all(),
        ]);
    }

    public function store(ZoomAccountRequest $request): RedirectResponse
    {
        ZoomAccount::create($request->validated());

        return redirect()->route('zoom-accounts.index')->with('success', 'Akun Zoom ditambahkan.');
    }

    public function update(ZoomAccountRequest $request, ZoomAccount $zoomAccount): RedirectResponse
    {
        $zoomAccount->update($request->validated());

        return redirect()->route('zoom-accounts.index')->with('success', 'Perubahan akun Zoom disimpan.');
    }

    public function destroy(ZoomAccount $zoomAccount): RedirectResponse
    {
        try {
            $zoomAccount->delete(); // soft delete; penjaga ada di event "deleting" model
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('zoom-accounts.index')->with('success', 'Akun Zoom dihapus.');
    }
}
