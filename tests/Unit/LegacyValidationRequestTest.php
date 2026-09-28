<?php

namespace Tests\Unit;

use App\Http\Requests\LegacyEndpointRequest;
use Tests\TestCase;

class LegacyValidationRequestTest extends TestCase
{
    public function test_login_keeps_required_email_and_password_rules(): void
    {
        $request = LegacyEndpointRequest::create('/api/auth/login.php', 'POST');

        $rules = $request->rules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('required', $rules['password']);
    }

    public function test_upload_rules_require_safe_extensions_and_size_limits(): void
    {
        $request = LegacyEndpointRequest::create('/api/books/upload_pdf.php', 'POST');

        $rules = $request->rules();

        $this->assertContains('mimes:pdf', $rules['pdf']);
        $this->assertContains('extensions:pdf', $rules['pdf']);
        $this->assertContains('max:51200', $rules['pdf']);
    }
}
