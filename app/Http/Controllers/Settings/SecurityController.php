<?php

namespace App\Http\Controllers\Settings;

use App\Http\AuthenticatedUserResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Passkey;
use UnexpectedValueException;

class SecurityController extends Controller
{
    public function __construct(private AuthenticatedUserResolver $authenticatedUser) {}

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $user = $this->authenticatedUser->resolve($request);
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
            'canManagePasskeys' => Features::canManagePasskeys(),
            'passkeys' => Features::canManagePasskeys()
                ? $user
                    ->passkeys()
                    ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
                    ->latest()
                    ->get()
                    ->map(fn (Passkey $passkey): array => $this->passkeyData($passkey))
                    ->values()
                    ->all()
                : [],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $request->ensureStateIsValid();

            $props['twoFactorEnabled'] = $user->hasEnabledTwoFactorAuthentication();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }

        return Inertia::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $this->authenticatedUser->resolve($request)->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }

    /** @return array<string, int|string|null> */
    private function passkeyData(Passkey $passkey): array
    {
        $createdAt = $passkey->created_at;
        if ($createdAt === null || ! $createdAt->isValid()) {
            throw new UnexpectedValueException('Passkey creation timestamp must be valid.');
        }

        return [
            'id' => $passkey->id, 'name' => $passkey->name, 'authenticator' => $passkey->authenticator,
            'created_at_diff' => $createdAt->diffForHumans(),
            'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
        ];
    }
}
