<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $table = 'Fines';
    protected $primaryKey = 'fine_id';
    public $timestamps = false;
    protected $fillable = ['transaction_id', 'user_email', 'amount', 'description', 'paid', 'payment_date'];
    public function transaction() { return $this->belongsTo(ApprovedTransaction::class, 'transaction_id', 'transaction_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_email', 'email'); }
    public function payments() { return $this->hasMany(Payment::class, 'fine_id', 'fine_id'); }
}