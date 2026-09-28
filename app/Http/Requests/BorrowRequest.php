<?php

namespace App\Http\Requests;


class BorrowRequest extends ApiFormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['isbn' => ['required', 'string', 'max:30']]; }
}