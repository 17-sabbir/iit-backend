<?php

namespace App\Http\Requests;


class ResetPasswordRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['token' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'string', 'min:8', 'confirmed']]; }
}