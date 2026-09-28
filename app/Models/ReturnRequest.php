<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReturnRequest extends Model { protected $table = 'Return_Requests'; protected $primaryKey = 'id'; public $timestamps = false; protected $fillable = ['transaction_id', 'requester_email', 'requested_at', 'status', 'processed_at', 'processed_by']; public function transaction() { return $this->belongsTo(ApprovedTransaction::class, 'transaction_id', 'transaction_id'); } public function requester() { return $this->belongsTo(User::class, 'requester_email', 'email'); } }