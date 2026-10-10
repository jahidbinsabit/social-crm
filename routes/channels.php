<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('admin-events', function ($user) {
    return $user->role === 'admin';
});

Broadcast::channel('staff.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id || $user->role === 'admin';
});
