<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class StaffSettingsController extends Controller
{
    public function showChangePasswordForm()
    {
        return view('staff.change-password');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        $user = Auth::user();

        if (!Auth::validate([
            'email' => $user->email,
            'password' => $request->input('current_password'),
        ])) {
            return response()->json(['success' => false, 'errors' => ['current_password' => ['Current password is incorrect']]], 422);
        }

        $user->update([
            'password' => bcrypt($request->input('new_password')),
        ]);

        return response()->json(['success' => true]);
    }
}
