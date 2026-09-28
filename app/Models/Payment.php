<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'Payments';
    protected $primaryKey = 'payment_id';
    public $timestamps = false;
    protected $fillable = ['fine_id', 'user_email', 'amount', 'status', 'gateway_txn_id', 'paid_at'];
    public function fine() { return $this->belongsTo(Fine::class, 'fine_id', 'fine_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_email', 'email'); }
}