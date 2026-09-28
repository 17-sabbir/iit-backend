<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegacyEndpointRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CourseController extends Controller
{
    public function index(LegacyEndpointRequest $request): JsonResponse
    {
        $query = DB::table('Courses');
        $search = trim($request->string('search')->toString());
        if ($search !== '') {
            $pattern = '%' . $search . '%';
            $query->where(fn ($courses) => $courses
                ->where('course_id', 'like', $pattern)
                ->orWhere('course_name', 'like', $pattern)
                ->orWhere('semester', 'like', $pattern));
        }
        $courses = $query->orderBy('course_id')->get(['course_id', 'course_name', 'semester']);
        return ApiResponse::success(['count' => $courses->count(), 'courses' => $courses]);
    }

    public function store(LegacyEndpointRequest $request): JsonResponse
    {
        $id = trim($request->string('course_id')->toString());
        DB::table('Courses')->updateOrInsert(
            ['course_id' => $id],
            ['course_name' => trim($request->string('course_name')->toString()), 'semester' => $request->input('semester')],
        );
        return ApiResponse::success(['course_id' => $id], 200, 'Course saved');
    }

    public function update(LegacyEndpointRequest $request): JsonResponse
    {
        $id = trim($request->string('course_id')->toString());
        $updated = DB::table('Courses')->where('course_id', $id)->update([
            'course_name' => trim($request->string('course_name')->toString()),
            'semester' => $request->input('semester'),
        ]);
        if ($updated === 0 && !DB::table('Courses')->where('course_id', $id)->exists()) {
            return ApiResponse::error('Course not found', 404);
        }
        return ApiResponse::success(['course_id' => $id], 200, 'Course updated successfully');
    }

    public function destroy(LegacyEndpointRequest $request): JsonResponse
    {
        $deleted = DB::table('Courses')->where('course_id', trim($request->string('course_id')->toString()))->delete();
        return $deleted
            ? ApiResponse::success([], 200, 'Course deleted successfully')
            : ApiResponse::error('Course not found', 404);
    }
}