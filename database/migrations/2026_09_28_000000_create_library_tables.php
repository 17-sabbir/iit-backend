<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Existing installations are upgraded by the additive migration that follows.
        if (Schema::hasTable('Users')) {
            return;
        }

        Schema::create('Users', function (Blueprint $table) {
            $table->string('email', 255)->primary();
            $table->string('name');
            $table->string('password_hash');
            $table->enum('role', ['Student', 'Teacher', 'Librarian', 'Director'])->default('Student');
            $table->string('contact', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('profile_image', 500)->nullable();
            $table->rememberToken();
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('last_login')->nullable();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
        Schema::create('Books', function (Blueprint $table) {
            $table->string('isbn', 30)->primary();
            $table->string('title'); $table->string('author'); $table->string('category', 120)->nullable();
            $table->string('publisher')->nullable(); $table->year('publication_year')->nullable(); $table->string('edition', 50)->nullable();
            $table->text('description')->nullable(); $table->string('pic_path', 500)->nullable();
            $table->string('language', 50)->default('English'); $table->string('keywords', 500)->nullable();
            $table->unsignedInteger('copies_total')->default(0); $table->unsignedInteger('copies_available')->default(0); $table->boolean('is_deleted')->default(false);
            $table->dateTime('created_at')->useCurrent(); $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index(['title', 'author', 'category']);
        });
        Schema::create('Courses', function (Blueprint $table) { $table->string('course_id', 50)->primary(); $table->string('course_name'); $table->string('semester', 50)->nullable(); });
        Schema::create('Course_Prerequisites', function (Blueprint $table) { $table->string('course_id', 50); $table->string('prerequisite_course_id', 50); $table->primary(['course_id', 'prerequisite_course_id']); });
        Schema::create('Course_Enrollments', function (Blueprint $table) { $table->string('email', 255); $table->string('course_id', 50); $table->string('role_in_course')->default('Student'); $table->primary(['email', 'course_id']); });
        Schema::create('Book_Courses', function (Blueprint $table) { $table->string('isbn', 30); $table->string('course_id', 50); $table->primary(['isbn', 'course_id']); });
        Schema::create('Shelves', function (Blueprint $table) { $table->increments('shelf_id'); $table->unsignedInteger('compartment')->default(0); $table->unsignedInteger('subcompartment')->default(0); $table->unsignedInteger('total_compartments')->default(0); $table->unsignedInteger('total_subcompartments')->default(0); $table->unsignedInteger('capacity')->default(0); $table->unsignedInteger('current_count')->default(0); $table->boolean('is_deleted')->default(false); $table->dateTime('created_at')->useCurrent(); $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate(); });
        Schema::create('Book_Copies', function (Blueprint $table) {
            $table->string('copy_id', 50)->primary(); $table->string('isbn', 30); $table->unsignedInteger('shelf_id')->nullable();
            $table->unsignedInteger('compartment_no')->nullable(); $table->unsignedInteger('subcompartment_no')->nullable();
            $table->enum('status', ['Available', 'Borrowed', 'Reserved', 'Lost', 'Discarded'])->default('Available');
            $table->string('condition_note')->nullable(); $table->boolean('is_deleted')->default(false); $table->dateTime('created_at')->useCurrent(); $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate(); $table->foreign('isbn')->references('isbn')->on('Books')->cascadeOnDelete();
        });
        Schema::create('Digital_Resources', function (Blueprint $table) { $table->increments('resource_id'); $table->string('isbn', 30)->nullable(); $table->string('file_name')->nullable(); $table->string('file_path', 500)->nullable(); $table->string('resource_type')->default('PDF'); $table->string('mime_type', 100)->nullable(); $table->unsignedBigInteger('size_bytes')->nullable(); $table->string('checksum', 128)->nullable(); $table->string('visibility')->default('Public'); $table->string('uploaded_by')->nullable(); $table->dateTime('uploaded_at')->useCurrent(); $table->boolean('is_deleted')->default(false); });
        Schema::create('Transaction_Requests', function (Blueprint $table) {
            $table->id('request_id'); $table->string('isbn', 30); $table->string('requested_copy_id', 50)->nullable(); $table->string('requester_email', 255);
            $table->dateTime('request_date')->useCurrent(); $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Cancelled'])->default('Pending');
            $table->string('reviewed_by', 255)->nullable(); $table->dateTime('reviewed_at')->nullable(); $table->text('notes')->nullable();
        });
        Schema::create('Approved_Transactions', function (Blueprint $table) {
            $table->id('transaction_id'); $table->unsignedBigInteger('request_id')->unique(); $table->string('isbn', 30)->nullable(); $table->string('copy_id', 50); $table->string('issued_by', 255)->nullable();
            $table->dateTime('issue_date')->useCurrent(); $table->dateTime('due_date'); $table->dateTime('return_date')->nullable();
            $table->enum('status', ['Borrowed', 'Returned', 'Overdue', 'Lost'])->default('Borrowed'); $table->dateTime('created_at')->useCurrent(); $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
        Schema::create('Reservations', function (Blueprint $table) {
            $table->id('reservation_id'); $table->string('isbn', 30); $table->string('user_email', 255); $table->unsignedInteger('queue_position');
            $table->enum('status', ['Active', 'Cancelled', 'Completed', 'Expired'])->default('Active'); $table->dateTime('created_at')->useCurrent(); $table->dateTime('notified_at')->nullable(); $table->dateTime('expires_at')->nullable(); $table->string('fulfilled_copy_id', 50)->nullable(); $table->unsignedBigInteger('fulfilled_txn_id')->nullable();
        });
        Schema::create('Return_Requests', function (Blueprint $table) { $table->increments('id'); $table->unsignedBigInteger('transaction_id'); $table->string('requester_email', 255); $table->dateTime('requested_at')->useCurrent(); $table->string('status')->default('Pending'); $table->dateTime('processed_at')->nullable(); $table->string('processed_by', 255)->nullable(); });
        Schema::create('Fines', function (Blueprint $table) { $table->id('fine_id'); $table->unsignedBigInteger('transaction_id')->nullable(); $table->string('user_email', 255); $table->decimal('amount', 10, 2); $table->string('description'); $table->boolean('paid')->default(false); $table->dateTime('payment_date')->nullable(); });
        Schema::create('Payments', function (Blueprint $table) { $table->id('payment_id'); $table->unsignedBigInteger('fine_id')->nullable(); $table->string('user_email', 255); $table->decimal('amount', 10, 2); $table->string('status')->default('Pending'); $table->string('gateway_txn_id')->nullable(); $table->dateTime('paid_at')->useCurrent(); });
        Schema::create('Notifications', function (Blueprint $table) { $table->id('notification_id'); $table->string('user_email', 255)->nullable(); $table->text('message'); $table->string('type')->default('System'); $table->string('action_url', 500)->nullable(); $table->boolean('isRead')->default(false); $table->boolean('is_read')->default(false); $table->dateTime('sent_at')->useCurrent(); $table->dateTime('expires_at')->nullable(); });
        Schema::create('Requests', function (Blueprint $table) { $table->id('request_id'); $table->string('requester_identifier', 255); $table->string('isbn', 30)->nullable(); $table->string('title'); $table->string('author')->nullable(); $table->string('category', 120)->nullable(); $table->string('publisher')->nullable(); $table->year('publication_year')->nullable(); $table->string('edition', 50)->nullable(); $table->string('pdf_path', 500)->nullable(); $table->string('file_name')->nullable(); $table->string('resource_type')->default('PDF'); $table->text('description')->nullable(); $table->string('status')->default('Pending'); $table->string('approved_by')->nullable(); $table->dateTime('approved_at')->nullable(); $table->dateTime('created_at')->useCurrent(); $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate(); });
        Schema::create('Students', function (Blueprint $table) { $table->string('email', 255)->primary(); $table->string('roll', 50)->unique(); $table->string('department', 120)->nullable(); $table->string('session', 50)->nullable(); $table->dateTime('created_at')->useCurrent(); $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate(); });
        Schema::create('Teachers', function (Blueprint $table) { $table->string('email', 255)->primary(); $table->string('designation', 120)->nullable(); $table->string('department', 120)->nullable(); $table->dateTime('created_at')->useCurrent(); $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate(); });
        Schema::create('Temp_User_Verification', function (Blueprint $table) { $table->string('email', 255); $table->string('otp_code', 20); $table->string('purpose'); $table->dateTime('created_at')->useCurrent(); $table->dateTime('expires_at'); $table->primary(['email', 'purpose']); });
        Schema::create('Reports', function (Blueprint $table) { $table->id('report_id'); $table->string('type'); $table->string('generated_by'); $table->dateTime('generated_at')->useCurrent(); $table->string('status')->default('Generating'); $table->string('file_path', 500)->nullable(); $table->json('filters')->nullable(); });
        Schema::create('Transaction_History', function (Blueprint $table) { $table->id('history_id'); $table->string('transaction_type'); $table->string('user_email'); $table->string('book_id', 30)->nullable(); $table->string('copy_id', 60)->nullable(); $table->decimal('amount', 10, 2)->nullable(); $table->string('status', 50)->nullable(); $table->text('description')->nullable(); $table->string('performed_by')->nullable(); $table->date('transaction_date'); $table->time('transaction_time'); $table->dateTime('created_at')->useCurrent(); });
        Schema::create('personal_access_tokens', function (Blueprint $table) { $table->id(); $table->morphs('tokenable'); $table->string('name'); $table->string('token', 64)->unique(); $table->text('abilities')->nullable(); $table->timestamp('last_used_at')->nullable(); $table->timestamp('expires_at')->nullable(); $table->timestamps(); });
    }

    public function down(): void
    {
        foreach (['personal_access_tokens', 'Transaction_History', 'Reports', 'Temp_User_Verification', 'Teachers', 'Students', 'Requests', 'Notifications', 'Payments', 'Fines', 'Return_Requests', 'Reservations', 'Approved_Transactions', 'Transaction_Requests', 'Digital_Resources', 'Book_Copies', 'Book_Courses', 'Shelves', 'Course_Enrollments', 'Course_Prerequisites', 'Courses', 'Books', 'Users'] as $table) Schema::dropIfExists($table);
    }
};