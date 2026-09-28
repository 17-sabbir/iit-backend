<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $table = 'Books';
    protected $primaryKey = 'isbn';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['isbn', 'title', 'author', 'category', 'publisher', 'publication_year', 'edition', 'description', 'pic_path'];
    public function copies() { return $this->hasMany(BookCopy::class, 'isbn', 'isbn'); }
    public function courses() { return $this->belongsToMany(Course::class, 'Book_Courses', 'isbn', 'course_id', 'isbn', 'course_id'); }
    public function digitalResources() { return $this->hasMany(DigitalResource::class, 'isbn', 'isbn'); }
    public function transactionRequests() { return $this->hasMany(TransactionRequest::class, 'isbn', 'isbn'); }
    public function reservations() { return $this->hasMany(Reservation::class, 'isbn', 'isbn'); }
}