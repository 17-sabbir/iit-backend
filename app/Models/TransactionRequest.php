<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionRequest extends Model
{
    protected $table = 'Transaction_Requests';
    protected $primaryKey = 'request_id';
    public $timestamps = false;
    protected $fillable = ['isbn', 'requested_copy_id', 'requester_email', 'request_date', 'status', 'reviewed_by', 'reviewed_at'];
    public function book() { return $this->belongsTo(Book::class, 'isbn', 'isbn'); }
    public function requester() { return $this->belongsTo(User::class, 'requester_email', 'email'); }
    public function transaction() { return $this->hasOne(ApprovedTransaction::class, 'request_id', 'request_id'); }
}