<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZoomAccountRequest;
use App\Models\ZoomAccount;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ZoomAccountController extends Controller
{
    public function index(): View
    {
        return view('zoom-accounts.index', ['accounts' => ZoomAccount::ordered()->get()]);
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
