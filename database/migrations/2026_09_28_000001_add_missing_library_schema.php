<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = [
            'Users' => [
                ['phone', fn (Blueprint $table) => $table->string('phone', 20)->nullable()],
                ['profile_image', fn (Blueprint $table) => $table->string('profile_image', 500)->nullable()],
                ['remember_token', fn (Blueprint $table) => $table->rememberToken()],
            ],
            'Books' => [
                ['language', fn (Blueprint $table) => $table->string('language', 50)->default('English')],
                ['keywords', fn (Blueprint $table) => $table->string('keywords', 500)->nullable()],
                ['copies_total', fn (Blueprint $table) => $table->unsignedInteger('copies_total')->default(0)],
                ['copies_available', fn (Blueprint $table) => $table->unsignedInteger('copies_available')->default(0)],
                ['is_deleted', fn (Blueprint $table) => $table->boolean('is_deleted')->default(false)],
            ],
            'Book_Copies' => [
                ['is_deleted', fn (Blueprint $table) => $table->boolean('is_deleted')->default(false)],
            ],
            'Digital_Resources' => [
                ['mime_type', fn (Blueprint $table) => $table->string('mime_type', 100)->nullable()],
                ['size_bytes', fn (Blueprint $table) => $table->unsignedBigInteger('size_bytes')->nullable()],
                ['checksum', fn (Blueprint $table) => $table->string('checksum', 128)->nullable()],
                ['visibility', fn (Blueprint $table) => $table->string('visibility')->default('Public')],
                ['is_deleted', fn (Blueprint $table) => $table->boolean('is_deleted')->default(false)],
            ],
            'Notifications' => [
                ['action_url', fn (Blueprint $table) => $table->string('action_url', 500)->nullable()],
                ['is_read', fn (Blueprint $table) => $table->boolean('is_read')->default(false)],
                ['expires_at', fn (Blueprint $table) => $table->dateTime('expires_at')->nullable()],
            ],
        ];

        foreach ($columns as $tableName => $tableColumns) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($tableColumns as [$column, $definition]) {
                if (!Schema::hasColumn($tableName, $column)) {
                    Schema::table($tableName, $definition);
                }
            }
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: this migration must never remove existing data or columns.
    }
};