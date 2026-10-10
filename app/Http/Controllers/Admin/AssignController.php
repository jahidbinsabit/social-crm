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
        $user->accounts()->detach($request->input("account_id"));

        return response()->json(["success" => true]);
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
        $assignedCount = 0;

        foreach ($accounts as $index => $account) {
            $assignedStaff = $staff[$index % $staffCount];
            $assignedStaff->accounts()->syncWithoutDetaching([$account->id]);
            $assignedCount++;
        }

        return response()->json([
            "success" => true,
            "message" => "Successfully auto-assigned {$assignedCount} account(s) across {$staffCount} staff member(s).",
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
