<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use App\Services\ApiTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseBackedApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Database unavailable. Start XAMPP/MySQL to run database-backed tests.');
        }

        foreach (['Users', 'Books', 'Transaction_Requests', 'Approved_Transactions'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Required table {$table} is not available.");
            }
        }
    }

    public function test_login_returns_sanctum_token_and_current_user_session(): void
    {
        $user = $this->createUser('student-test@iit.edu', 'Student');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password-123',
        ]);

        $login->assertOk()->assertJsonPath('success', true)->assertJsonPath('role', 'Student');
        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_login_validation_keeps_legacy_400_error_contract(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_preregistered_student_can_complete_registration_otp_workflow(): void
    {
        $email = 'migration-check-' . bin2hex(random_bytes(4)) . '@example.test';
        $prereg = DB::connection('preregistration');
        $authTemp = DB::connection('auth_temp');
        $prereg->beginTransaction();
        $authTemp->beginTransaction();

        try {
            $prereg->table('PreReg_Students')->insert([
                'email' => $email,
                'roll' => 'MIG-' . strtoupper(bin2hex(random_bytes(3))),
                'full_name' => 'Migration Test Student',
                'contact' => '01700000000',
                'session' => '2025-2026',
            ]);

            $send = $this->postJson('/api/auth/send_register_otp.php', ['email' => $email]);
            $send->assertOk()->assertJsonPath('success', true)->assertJsonPath('role', 'Student');
            $otp = $authTemp->table('Temp_User_Verification')->where('email', $email)->where('purpose', 'EmailVerification')->value('otp_code');
            $this->assertNotEmpty($otp);

            $this->postJson('/api/auth/verify_email.php', ['email' => $email, 'otp' => $otp])
                ->assertOk()->assertJsonPath('success', true);
            $this->postJson('/api/auth/set_password.php', ['email' => $email, 'new_password' => 'MigrationPass123!'])
                ->assertOk()->assertJsonPath('success', true);
            $this->postJson('/api/auth/login.php', ['email' => $email, 'password' => 'MigrationPass123!'])
                ->assertOk()->assertJsonPath('success', true)->assertJsonPath('role', 'Student');
        } finally {
            Cache::store()->forget('auth-workflow:registration:' . hash('sha256', $email));
            $authTemp->rollBack();
            $prereg->rollBack();
        }
    }

    public function test_library_settings_read_and_update_use_the_preregistration_connection(): void
    {
        $email = 'settings-test-' . bin2hex(random_bytes(4)) . '@example.test';
        $user = $this->createUser($email, 'Librarian');
        $token = app(ApiTokenService::class)->issue($user);
        $prereg = DB::connection('preregistration');
        $prereg->beginTransaction();

        try {
            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->putJson('/api/settings/update_library_settings.php', ['settings' => ['library_phone' => 'TEST-000']])
                ->assertOk()->assertJsonPath('success', true);
            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/settings/get_library_settings.php')
                ->assertOk()->assertJsonPath('settings.library_phone', 'TEST-000');
        } finally {
            $prereg->rollBack();
            app(ApiTokenService::class)->revokeAll($user);
        }
    }

    public function test_all_report_types_return_json_without_legacy_dispatch(): void
    {
        $user = $this->createUser('reports-test-' . bin2hex(random_bytes(4)) . '@example.test', 'Librarian');
        $token = app(ApiTokenService::class)->issue($user);
        try {
            foreach (['most_borrowed', 'most_requested', 'semester_wise', 'session_wise'] as $reportType) {
                $this->withHeader('Authorization', 'Bearer ' . $token)
                    ->postJson('/api/reports/generate_report.php', ['report_type' => $reportType, 'format' => 'json'])
                    ->assertOk()->assertJsonPath('success', true)->assertJsonPath('report_type', $reportType);
            }
        } finally {
            app(ApiTokenService::class)->revokeAll($user);
        }
    }

    public function test_payment_read_and_processing_match_live_payment_schema(): void
    {
        $user = $this->createUser('payment-test-' . bin2hex(random_bytes(4)) . '@example.test', 'Student');
        $token = app(ApiTokenService::class)->issue($user);
        $fineId = DB::table('Fines')->insertGetId([
            'user_email' => $user->email,
            'amount' => 25.50,
            'description' => 'Migration payment test',
            'paid' => false,
        ]);

        try {
            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/payments/get_user_fines.php?user_email=' . urlencode($user->email))
                ->assertOk()->assertJsonPath('success', true)->assertJsonPath('fines.0.fine_id', $fineId);

            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/payments/process_payment.php', [
                    'user_email' => $user->email,
                    'fine_ids' => [$fineId],
                    'transaction_ids' => [],
                    'payment_method' => 'cash',
                ])
                ->assertOk()->assertJsonPath('success', true)->assertJsonPath('amount', 25.5);

            $this->assertDatabaseHas('Fines', ['fine_id' => $fineId, 'paid' => 1]);
            $this->assertDatabaseHas('Payments', ['fine_id' => $fineId, 'user_email' => $user->email, 'status' => 'Completed']);
        } finally {
            app(ApiTokenService::class)->revokeAll($user);
        }
    }

    public function test_student_is_denied_from_librarian_routes(): void
    {
        $user = $this->createUser('student-authz@iit.edu', 'Student');
        $token = app(ApiTokenService::class)->issue($user);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/librarian/dashboard_stats.php')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_book_model_crud_persists_and_updates_data(): void
    {
        $book = new Book();
        $book->forceFill(['isbn' => 'TEST-CRUD-001', 'title' => 'Original title', 'author' => 'Test author']);
        $book->save();

        $this->assertDatabaseHas('Books', ['isbn' => 'TEST-CRUD-001', 'title' => 'Original title']);

        $book->update(['title' => 'Updated title']);
        $this->assertDatabaseHas('Books', ['isbn' => 'TEST-CRUD-001', 'title' => 'Updated title']);

        $book->delete();
        $this->assertDatabaseMissing('Books', ['isbn' => 'TEST-CRUD-001']);
    }

    public function test_student_can_submit_a_borrow_request(): void
    {
        $user = $this->createUser('borrow-test@iit.edu', 'Student');
        $book = new Book();
        $book->forceFill(['isbn' => 'TEST-BORROW-001', 'title' => 'Borrowable book', 'author' => 'Test author']);
        $book->save();
        $token = app(ApiTokenService::class)->issue($user);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/borrow/requests', ['isbn' => $book->isbn])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['request_id']);
    }

    private function createUser(string $email, string $role): User
    {
        $user = new User();
        $user->forceFill([
            'email' => $email,
            'name' => 'Test User',
            'password_hash' => Hash::make('password-123'),
            'role' => $role,
            'contact' => null,
        ]);
        $user->save();
        return $user;
    }
}
