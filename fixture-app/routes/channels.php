<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('teams.{teamId}', function (User $user, int $teamId): bool {
    return Team::query()->whereKey($teamId)->where('owner_id', $user->id)->exists();
});
