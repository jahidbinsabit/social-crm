<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignController extends Controller
{
    public function index()
    {
        $staff = User::where("role", "staff")
            ->where("status", "active")
            ->with("accounts")
            ->get();

        $accounts = Account::where("status", "active")
            ->orderByRaw("FIELD(platform, 'telegram', 'whatsapp')")
            ->orderByDesc("created_at")
            ->get();

        return view("admin.assign", compact("staff", "accounts"));
    }

    public function assign(Request $request): JsonResponse
    {
        $request->validate([
            "staff_id" => "required|exists:users,id",
            "account_id" => "required|exists:accounts,id",
        ]);

        $user = User::findOrFail($request->input("staff_id"));

        // Only ACTIVE STAFF may receive assignments. Previously any user id
        // was accepted — admin/inactive users got account_staff rows that
        // drive media access, chat access and broadcast fan-out.
        if (($error = $this->eligibleStaffError($user)) !== null) {
            return response()->json(["success" => false, "message" => $error], 422);
        }

        $user->accounts()->syncWithoutDetaching([$request->input("account_id")]);

        return response()->json(["success" => true]);
    }

    public function deassign(Request $request): JsonResponse
    {
        $request->validate([
            "staff_id" => "required|exists:users,id",
            "account_id" => "required|exists:accounts,id",
        ]);

        $user = User::findOrFail($request->input("staff_id"));

        if (($error = $this->eligibleStaffError($user)) !== null) {
            return response()->json(["success" => false, "message" => $error], 422);
        }

        $user->accounts()->detach($request->input("account_id"));

        return response()->json(["success" => true]);
    }

    /**
     * Return an error message when the user may not hold assignments,
     * or null when they are an active staff member.
     */
    private function eligibleStaffError(User $user): ?string
    {
        if ($user->role !== "staff") {
            return "Only staff members can be assigned accounts.";
        }

        if ($user->status !== "active") {
            return "Inactive staff members cannot be assigned accounts.";
        }

        return null;
    }

    public function autoAssign(Request $request): JsonResponse
    {
        $platform = $request->input("platform");

        $staff = User::where("role", "staff")
            ->where("status", "active")
            ->get();

        if ($staff->isEmpty()) {
            return response()->json([
                "success" => false,
                "message" => "No active staff members found to assign accounts to.",
            ], 422);
        }

        $accountQuery = Account::where("status", "active");
        if ($platform) {
            $accountQuery->where("platform", $platform);
        }
        $accounts = $accountQuery->get();

        if ($accounts->isEmpty()) {
            return response()->json([
                "success" => false,
                "message" => "No active accounts found to assign.",
            ], 422);
        }

        $staffCount = $staff->count();

        // INCREMENTAL: only accounts with NO staff assignment at all are
        // distributed. Existing account_staff rows are never touched — the
        // old loop walked every account and added another staff member to
        // each one on every run, piling up duplicate access.
        $unassigned = $accounts->filter(
            fn($account) => $account->staff()->doesntExist()
        )->values();

        if ($unassigned->isEmpty()) {
            return response()->json([
                "success" => true,
                "message" => "All active accounts are already assigned. Nothing changed.",
                "assigned" => 0,
            ]);
        }

        // Balance the new accounts across staff by current workload, so the
        // staff member with the fewest accounts gets the next one.
        $loads = $staff->mapWithKeys(
            fn($member) => [$member->id => $member->accounts()->count()]
        );

        $assignedCount = 0;

        foreach ($unassigned as $account) {
            $targetId = $loads->sort()->keys()->first();
            $target = $staff->firstWhere("id", $targetId);
            $target->accounts()->syncWithoutDetaching([$account->id]);
            $loads[$targetId]++;
            $assignedCount++;
        }

        return response()->json([
            "success" => true,
            "message" => "Successfully auto-assigned {$assignedCount} previously unassigned account(s) across {$staffCount} staff member(s). Existing assignments were left unchanged.",
            "assigned" => $assignedCount,
        ]);
    }

    public function staffAccounts(Request $request, User $staff): JsonResponse
    {
        $platform = $request->query("platform");

        // Get all active accounts, optionally filtered by platform
        $query = Account::where("status", "active");
        if ($platform) {
            $query->where("platform", $platform);
        }

        $accounts = $query->orderByRaw("FIELD(platform, 'telegram', 'whatsapp')")
            ->orderByDesc("created_at")
            ->get(["id", "platform", "account_name", "phone_number", "status"]);

        // Get IDs of accounts already assigned to this staff member
        $assignedIds = $staff->accounts()->where("status", "active")->pluck("accounts.id")->toArray();

        // Add assignment status to each account
        foreach ($accounts as $account) {
            $account->assigned = in_array($account->id, $assignedIds);
        }

        return response()->json($accounts);
    }
}
