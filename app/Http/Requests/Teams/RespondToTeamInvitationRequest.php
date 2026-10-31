<?php

namespace App\Http\Requests\Teams;

use App\Http\AuthenticatedUserResolver;
use App\Rules\ValidTeamInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RespondToTeamInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(AuthenticatedUserResolver $authenticatedUser): array
    {
        return [
            'invitation' => ['required', new ValidTeamInvitation($authenticatedUser->resolve($this))],
        ];
    }

    /**
     * Get the validation data from the request.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge(parent::validationData(), [
            'invitation' => $this->route('invitation'),
        ]);
    }
}
