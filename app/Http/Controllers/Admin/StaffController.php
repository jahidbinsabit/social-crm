<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';

        $user->update([
            'status' => $newStatus,
        ]);

        if ($newStatus === 'inactive') {
            $this->revokeSessions($user);
        }

        return back()->with('success', 'Staff status updated');
    }

    /**
     * Cut a deactivated user's live access as far as the app layer allows.
     *
     * What this does:
     *  - Deletes the user's rows from the `sessions` table when it exists
     *    (database session driver), so stored sessions die immediately.
     *
     * What covers the rest:
     *  - CheckUserActive already logs the user out on their next web
     *    request once status != active.
     *  - routes/channels.php now requires status === 'active' for both
     *    'admin-events' and 'staff.{id}', so a deactivated user cannot
     *    (re-)authorize a realtime subscription. An already-open socket is
     *    not force-closed server-side — Reverb does not re-run channel
     *    auth on a live connection; that residual window closes when the
     *    socket reconnects. (With the file session driver there are no
     *    session rows to delete; the two checks above are the enforcement.)
     */
    private function revokeSessions(User $user): void
    {
        try {
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to revoke sessions for deactivated user', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
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
