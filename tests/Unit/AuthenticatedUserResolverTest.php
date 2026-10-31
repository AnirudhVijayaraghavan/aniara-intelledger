<?php

declare(strict_types=1);

use App\Http\AuthenticatedUserResolver;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;

test('the principal resolver returns the exact application user from one request resolution', function () {
    $user = new User;
    $request = new Request;
    $resolutions = 0;
    $request->setUserResolver(function () use ($user, &$resolutions) {
        $resolutions++;

        return $user;
    });

    expect((new AuthenticatedUserResolver)->resolve($request))->toBe($user);
    expect($resolutions)->toBe(1);
});

test('the principal resolver rejects absent and incompatible request principals', function (mixed $principal) {
    $request = new Request;
    $request->setUserResolver(fn () => $principal);

    expect(fn () => (new AuthenticatedUserResolver)->resolve($request))
        ->toThrow(AuthenticationException::class, 'Unauthenticated.');
})->with([
    'missing' => [null],
    'another authenticatable' => [new GenericUser(['id' => 1])],
    'an arbitrary object' => [new stdClass],
    'a user id' => ['1'],
]);

test('a reused principal resolver reads each explicit request without retaining an earlier user', function () {
    $resolver = new AuthenticatedUserResolver;
    $first = new Request;
    $firstUser = new User;
    $first->setUserResolver(fn () => $firstUser);
    $second = new Request;
    $secondUser = new User;
    $second->setUserResolver(fn () => $secondUser);
    $guest = new Request;
    $guest->setUserResolver(fn () => null);

    expect($resolver->resolve($first))->toBe($firstUser);
    expect($resolver->resolve($second))->toBe($secondUser);
    expect(fn () => $resolver->resolve($guest))->toThrow(AuthenticationException::class);
});
