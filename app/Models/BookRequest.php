<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BookRequest extends Model { protected $table = 'Requests'; protected $primaryKey = 'request_id'; protected $fillable = ['requester_identifier', 'isbn', 'title', 'author', 'category', 'publisher', 'publication_year', 'edition', 'pdf_path', 'file_name', 'resource_type', 'description', 'status', 'approved_by', 'approved_at']; public function requester() { return $this->belongsTo(User::class, 'requester_identifier', 'email'); } public function book() { return $this->belongsTo(Book::class, 'isbn', 'isbn'); } }