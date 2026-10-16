<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Avisos de cambios de dietas y despachos (App\Events\DietsChanged): quien ve alguna de esas pantallas
Broadcast::channel(App\Events\DietsChanged::CHANNEL, function ($user) {
    return $user->is_active && $user->canAny(['viewActiveDiets', 'viewDiets', 'viewDispatches', 'confirmDelivery', 'confirmReception']);
});
