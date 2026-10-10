<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index()
    {
        $staff = User::where('role', 'staff')->with('accounts')->get();

        return view('admin.staff', compact('staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => 'staff',
            'status' => 'active',
        ]);

        return back()->with('success', 'Staff member created successfully');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->role === 'admin') {
            return back()->with('error', 'Cannot modify admin');
        }

        $user->update([
            'status' => $user->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'Staff status updated');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->role === 'admin') {
            abort(403);
        }

        $user->accounts()->detach();
        $user->delete();

        return back()->with('success', 'Staff member deleted');
    }
}
