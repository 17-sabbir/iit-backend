<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Student extends Model { protected $table = 'Students'; protected $primaryKey = 'email'; public $incrementing = false; protected $keyType = 'string'; protected $fillable = ['email', 'roll', 'department', 'session']; public function user() { return $this->belongsTo(User::class, 'email', 'email'); } }