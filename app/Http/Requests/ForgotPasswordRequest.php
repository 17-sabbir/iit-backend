<?php

namespace App\Http\Requests;


class ForgotPasswordRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['email' => ['required', 'email']]; }
}