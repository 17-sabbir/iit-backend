<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DigitalResource extends Model { protected $table = 'Digital_Resources'; protected $primaryKey = 'resource_id'; public $timestamps = false; protected $fillable = ['isbn', 'file_name', 'file_path', 'resource_type', 'mime_type', 'size_bytes', 'checksum', 'visibility', 'uploaded_by', 'is_deleted']; public function book() { return $this->belongsTo(Book::class, 'isbn', 'isbn'); } public function uploader() { return $this->belongsTo(User::class, 'uploaded_by', 'email'); } }