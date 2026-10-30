<?php

declare(strict_types=1);

namespace App\Http;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

final class AuthenticatedUserResolver
{
    public function resolve(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
