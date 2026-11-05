<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin-events', function ($user) {
    // Active check matters: a deactivated admin/staff session must not be
    // able to (re-)authorize a realtime subscription and keep receiving
    // live messages and pairing codes.
    return $user->role === 'admin' && $user->status === 'active';
});

Broadcast::channel('staff.{id}', function ($user, $id) {
    if ($user->status !== 'active') {
        return false;
    }

    return (int) $user->id === (int) $id || $user->role === 'admin';
});
