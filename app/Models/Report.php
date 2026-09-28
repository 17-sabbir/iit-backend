<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Report extends Model { protected $table = 'Reports'; protected $primaryKey = 'report_id'; public $timestamps = false; protected $casts = ['filters' => 'array', 'generated_at' => 'datetime']; protected $fillable = ['type', 'generated_by', 'generated_at', 'status', 'file_path', 'filters']; public function generator() { return $this->belongsTo(User::class, 'generated_by', 'email'); } }