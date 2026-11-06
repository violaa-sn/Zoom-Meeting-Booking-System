<?php

namespace App\Http\Controllers;

use App\Http\Requests\DepartmentRequest;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = Department::withCount('users')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.addcslashes(trim($request->query('search')), '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term));
            })
            ->latest('id') // yang baru ditambahkan tampil paling atas
            ->get();

        return view('departments.index', ['departments' => $departments]);
    }

    public function store(DepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return redirect()->route('departments.index')->with('success', 'Departemen ditambahkan.');
    }

    public function update(DepartmentRequest $request, Department $department): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()->route('departments.index')->with('success', 'Perubahan departemen disimpan.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        // users.department_id memakai restrictOnDelete; beri pesan jelas sebelum database menolak
        $count = $department->users()->count();
        if ($count > 0) {
            return back()->with('error', "Departemen {$department->name} masih dipakai {$count} pengguna. Pindahkan mereka ke departemen lain dulu.");
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Departemen dihapus.');
    }
}
