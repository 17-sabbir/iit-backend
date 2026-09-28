<?php

namespace App\Http\Requests;


class LegacyEndpointRequest extends ApiFormRequest
{
    protected $stopOnFirstFailure = true;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('OPTIONS')) {
            return [];
        }

        $path = $this->endpointPath();
        if ($path === 'librarian/manage_shelves.php') {
            return self::shelfRules($this->method());
        }

        return self::rulesFor($path);
    }

    public function endpointPath(): string
    {
        $path = (string) ($this->route('path') ?: $this->path());
        return ltrim(preg_replace('#^api/#', '', $path), '/');
    }

    private static function rulesFor(string $path): array
    {
        return match ($path) {
            'v1/auth/register' => ['email' => ['required', 'email']],
            'v1/auth/forgot-password' => ['email' => ['required', 'email']],
            'v1/auth/reset-password' => ['email' => ['required', 'email'], 'otp' => ['required', 'string'], 'new_password' => ['required', 'string', 'min:8']],
            'auth/login.php' => ['email' => ['required', 'email'], 'password' => ['required', 'string']],
            'auth/register.php', 'auth/send_register_otp.php' => ['email' => ['required', 'email']],
            'auth/verify_email.php', 'auth/verify_reset_otp.php' => ['email' => ['required', 'email'], 'otp' => ['required', 'string']],
            'auth/send_reset_otp.php' => ['email' => ['required', 'email']],
            'auth/reset_password.php' => ['email' => ['required', 'email'], 'otp' => ['required', 'string'], 'new_password' => ['required', 'string', 'min:8']],
            'auth/set_password.php' => ['email' => ['required', 'email'], 'new_password' => ['required', 'string', 'min:8']],
            'auth/change_password.php' => ['email' => ['required', 'email'], 'current_password' => ['required', 'string'], 'new_password' => ['required', 'string', 'min:8'], 'confirm_password' => ['required', 'same:new_password']],
            'auth/get_profile.php' => ['email' => ['required', 'email']],
            'auth/get_notifications.php' => ['email' => ['required', 'email'], 'limit' => ['nullable', 'integer', 'min:1']],
            'auth/mark_notification_read.php' => ['user_email' => ['required', 'email'], 'notification_id' => ['required_without:mark_all', 'integer', 'min:1'], 'mark_all' => ['nullable', 'boolean']],
            'auth/delete_notification.php' => ['user_email' => ['required', 'email'], 'notification_id' => ['required', 'integer', 'min:1']],
            'auth/get_image.php', 'serve_image.php' => ['path' => ['required', 'string']],
            'auth/upload_profile_image.php' => ['email' => ['required', 'email'], 'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:jpg,jpeg,png,gif,webp', 'max:5120']],

            'books/add_book.php' => ['title' => ['required', 'string'], 'author' => ['required', 'string'], 'isbn' => ['required', 'string'], 'copies_total' => ['nullable', 'integer', 'min:0'], 'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:jpg,jpeg,png,gif,webp', 'max:5120']],
            'books/update_book.php' => ['isbn' => ['required', 'string']],
            'books/delete_book.php' => ['isbn' => ['required', 'string']],
            'books/request_book.php' => ['title' => ['required', 'string'], 'user_email' => ['required', 'email']],
            'books/approve_request.php', 'books/decline_request.php', 'books/get_request_details.php' => ['request_id' => ['required', 'integer', 'min:1']],
            'books/cancel_reservation.php' => ['reservation_id' => ['required', 'integer', 'min:1']],
            'books/reserve_book.php' => ['isbn' => ['required', 'string'], 'user_email' => ['required', 'email']],
            'books/get_book_copies.php', 'books/get_book_courses.php', 'books/get_book_status.php' => ['isbn' => ['required', 'string']],
            'books/get_user_reservations.php' => ['email' => ['required', 'email']],
            'books/download_pdf.php' => ['isbn' => ['required', 'string']],
            'books/upload_pdf.php', 'books/upload_request_pdf.php' => ['pdf' => ['required', 'file', 'mimes:pdf', 'extensions:pdf', 'max:51200']],
            'books/upload_cover_image.php' => ['image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:jpg,jpeg,png,gif,webp', 'max:5120']],
            'books/update_book_image.php' => ['isbn' => ['required', 'string'], 'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'extensions:jpg,jpeg,png,gif,webp', 'max:5120']],

            'borrow/borrow_book.php', 'borrow/request_borrow.php' => ['isbn' => ['required', 'string', 'max:30']],
            'borrow/cancel_request.php' => ['user_email' => ['required_without:email', 'email'], 'email' => ['required_without:user_email', 'email'], 'request_id' => ['required_without:transaction_id', 'integer', 'min:1'], 'transaction_id' => ['required_without:request_id', 'integer', 'min:1']],
            'borrow/get_user_transactions.php' => ['email' => ['required', 'email'], 'status' => ['nullable', 'in:all,borrowed,returned,reserved,pending']],
            'borrow/request_return.php' => ['transaction_id' => ['required', 'integer', 'min:1'], 'user_email' => ['required', 'email']],
            'borrow/return_book.php' => ['transaction_id' => ['required_without:copy_id', 'integer', 'min:1'], 'copy_id' => ['required_without:transaction_id', 'string'], 'damage_fine' => ['nullable', 'numeric', 'min:0'], 'book_condition' => ['nullable', 'string']],

            'courses/add_course.php', 'courses/edit_course.php' => ['course_id' => ['required', 'string'], 'course_name' => ['required', 'string'], 'semester' => ['nullable', 'string']],
            'courses/delete_course.php' => ['course_id' => ['required', 'string']],

            'librarian/get_available_copies.php' => ['isbn' => ['required', 'string']],
            'librarian/approve_borrow_request.php' => ['request_id' => ['required', 'integer', 'min:1'], 'copy_id' => ['required', 'string']],
            'librarian/approve_return_request.php' => ['transaction_id' => ['required', 'integer', 'min:1']],
            'librarian/reject_borrow_request.php' => ['request_id' => ['required', 'integer', 'min:1']],
            'librarian/get_requests.php' => ['type' => ['nullable', 'in:borrow,return,reserve,addition'], 'search' => ['nullable', 'string']],
            'payments/get_payments.php', 'payments/get_payment_history.php', 'payments/get_user_fines.php' => ['user_email' => ['required', 'email'], 'startDate' => ['nullable', 'date'], 'endDate' => ['nullable', 'date']],
            'payments/process_payment.php' => ['user_email' => ['required', 'email'], 'fine_ids' => ['required_without_all:transaction_ids', 'array'], 'transaction_ids' => ['required_without_all:fine_ids', 'array'], 'payment_method' => ['nullable', 'string']],
            'reports/generate_report.php' => [
                'report_type' => ['required', 'in:most_borrowed,most_requested,semester_wise,session_wise'],
                'start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date'],
                'semester' => ['nullable', 'string'], 'session' => ['nullable', 'string'],
                'format' => ['nullable', 'in:json,csv,pdf'],
            ],
            'settings/update_library_settings.php' => ['settings' => ['required', 'array']],
            default => [],
        };
    }

    private static function shelfRules(string $method): array
    {
        if (!in_array($method, ['POST', 'PUT'], true)) {
            return [];
        }

        return [
            'shelf_id' => ['required', 'integer', 'min:1'],
            'compartment' => ['required', 'integer', 'min:1'],
            'subcompartment' => ['required', 'integer', 'min:1'],
        ];
    }
}