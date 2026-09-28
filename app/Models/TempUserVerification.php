<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TempUserVerification extends Model { protected $table = 'Temp_User_Verification'; protected $primaryKey = null; public $incrementing = false; public $timestamps = false; protected $fillable = ['email', 'otp_code', 'purpose', 'created_at', 'expires_at']; protected $casts = ['created_at' => 'datetime', 'expires_at' => 'datetime']; }