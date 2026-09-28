<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'Notifications';
    protected $primaryKey = 'notification_id';
    public $timestamps = false;
    protected $fillable = ['user_email', 'message', 'type', 'isRead', 'sent_at'];
    protected $casts = ['isRead' => 'boolean', 'sent_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class, 'user_email', 'email'); }
}