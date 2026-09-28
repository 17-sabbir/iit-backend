<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Teacher extends Model { protected $table = 'Teachers'; protected $primaryKey = 'email'; public $incrementing = false; protected $keyType = 'string'; protected $fillable = ['email', 'designation', 'department']; public function user() { return $this->belongsTo(User::class, 'email', 'email'); } }