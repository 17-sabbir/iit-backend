<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Auth\Passwords\CanResetPassword;

class User extends Authenticatable implements CanResetPasswordContract
{
    use HasApiTokens, Notifiable, CanResetPassword;

    protected $table = 'Users';
    protected $primaryKey = 'email';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['name', 'contact', 'profile_image'];
    protected $hidden = ['password_hash', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime', 'last_login' => 'datetime'];

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public function student() { return $this->hasOne(Student::class, 'email', 'email'); }
    public function teacher() { return $this->hasOne(Teacher::class, 'email', 'email'); }
    public function borrowRequests() { return $this->hasMany(TransactionRequest::class, 'requester_email', 'email'); }
    public function transactions() { return $this->hasManyThrough(ApprovedTransaction::class, TransactionRequest::class, 'requester_email', 'request_id', 'email', 'request_id'); }
    public function reservations() { return $this->hasMany(Reservation::class, 'user_email', 'email'); }
    public function fines() { return $this->hasMany(Fine::class, 'user_email', 'email'); }
    public function payments() { return $this->hasMany(Payment::class, 'user_email', 'email'); }
    public function notifications() { return $this->hasMany(Notification::class, 'user_email', 'email'); }
}