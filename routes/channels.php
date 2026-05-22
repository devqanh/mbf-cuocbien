<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel dùng cho sheet Items — chỉ user đã đăng nhập mới được listen
Broadcast::channel('items-sheet', function ($user) {
    return $user !== null;
});
