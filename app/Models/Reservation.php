<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $table = 'Reservations';
    protected $primaryKey = 'reservation_id';
    public $timestamps = false;
    protected $fillable = ['isbn', 'user_email', 'queue_position', 'status', 'created_at', 'notified_at', 'expires_at'];
    protected $casts = ['created_at' => 'datetime', 'notified_at' => 'datetime', 'expires_at' => 'datetime'];
    public function book() { return $this->belongsTo(Book::class, 'isbn', 'isbn'); }
    public function user() { return $this->belongsTo(User::class, 'user_email', 'email'); }
}