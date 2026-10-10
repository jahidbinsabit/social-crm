<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StaffChannelController extends Controller
{
    public function index()
    {
        return view('staff.channel');
    }

    public function setChannel(Request $request)
    {
        $request->validate([
            'channel' => 'required|in:telegram,whatsapp',
        ]);

        $request->session()->put('channel', $request->input('channel'));

        return redirect()->route('staff.dashboard');
    }
}
