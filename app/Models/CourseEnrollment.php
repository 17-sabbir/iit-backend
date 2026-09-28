<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CourseEnrollment extends Model { protected $table = 'Course_Enrollments'; protected $primaryKey = null; public $incrementing = false; public $timestamps = false; protected $fillable = ['email', 'course_id', 'role_in_course']; public function course() { return $this->belongsTo(Course::class, 'course_id', 'course_id'); } public function user() { return $this->belongsTo(User::class, 'email', 'email'); } }