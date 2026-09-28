<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookCopy extends Model
{
    protected $table = 'Book_Copies';
    protected $primaryKey = 'copy_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['copy_id', 'isbn', 'shelf_id', 'compartment_no', 'subcompartment_no', 'status', 'condition_note'];
    public function book() { return $this->belongsTo(Book::class, 'isbn', 'isbn'); }
    public function shelf() { return $this->belongsTo(Shelf::class, 'shelf_id', 'shelf_id'); }
    public function transactions() { return $this->hasMany(ApprovedTransaction::class, 'copy_id', 'copy_id'); }
}