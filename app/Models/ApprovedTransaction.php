<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovedTransaction extends Model
{
    protected $table = 'Approved_Transactions';
    protected $primaryKey = 'transaction_id';
    public $timestamps = false;
    protected $fillable = ['request_id', 'copy_id', 'issued_by', 'issue_date', 'due_date', 'return_date', 'status'];
    protected $casts = ['issue_date' => 'datetime', 'due_date' => 'datetime', 'return_date' => 'datetime'];
    public function request() { return $this->belongsTo(TransactionRequest::class, 'request_id', 'request_id'); }
    public function copy() { return $this->belongsTo(BookCopy::class, 'copy_id', 'copy_id'); }
    public function fines() { return $this->hasMany(Fine::class, 'transaction_id', 'transaction_id'); }
    public function returnRequests() { return $this->hasMany(ReturnRequest::class, 'transaction_id', 'transaction_id'); }
}