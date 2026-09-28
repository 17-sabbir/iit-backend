<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $table = 'Courses';
    protected $primaryKey = 'course_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['course_id', 'course_name', 'semester'];
    public function books() { return $this->belongsToMany(Book::class, 'Book_Courses', 'course_id', 'isbn', 'course_id', 'isbn'); }
    public function prerequisites() { return $this->belongsToMany(self::class, 'Course_Prerequisites', 'course_id', 'prerequisite_course_id', 'course_id', 'course_id'); }
    public function enrollments() { return $this->hasMany(CourseEnrollment::class, 'course_id', 'course_id'); }
}