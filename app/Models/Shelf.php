<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Shelf extends Model { protected $table = 'Shelves'; protected $primaryKey = 'shelf_id'; protected $fillable = ['compartment', 'subcompartment', 'total_compartments', 'total_subcompartments', 'capacity', 'current_count', 'is_deleted']; public function copies() { return $this->hasMany(BookCopy::class, 'shelf_id', 'shelf_id'); } }