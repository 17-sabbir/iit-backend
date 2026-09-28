<?php

namespace App\Http\Requests;


class RegisterRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['email' => ['required', 'email', 'unique:Users,email'], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'min:8'], 'contact' => ['nullable', 'string', 'max:20']]; }
}