<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Http\Request;

class BookCatalogService
{
    public function search(Request $request): array
    {
        $query = Book::query()->where('title', 'not like', '[DELETED]%');

        if ($request->filled('search')) {
            $term = '%' . $request->string('search')->toString() . '%';
            $query->where(fn ($books) => $books
                ->where('title', 'like', $term)
                ->orWhere('author', 'like', $term)
                ->orWhere('isbn', 'like', $term));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('course_id')) {
            $query->whereHas('courses', fn ($courses) => $courses->where('Courses.course_id', $request->string('course_id')->toString()));
        }

        if ($request->filled('semester')) {
            $query->whereHas('courses', fn ($courses) => $courses->where('Courses.semester', $request->string('semester')->toString()));
        }

        if ($request->input('book_type') === 'Digital') {
            $query->whereHas('digitalResources', fn ($resources) => $resources->where('resource_type', 'PDF'));
        } elseif ($request->input('book_type') === 'Physical') {
            $query->whereHas('copies');
        }

        $books = $query
            ->with([
                'courses' => fn ($courses) => $courses->orderBy('Courses.course_id'),
                'digitalResources' => fn ($resources) => $resources->where('resource_type', 'PDF'),
            ])
            ->withCount([
                'copies as copies_total',
                'copies as copies_available' => fn ($copies) => $copies->where('status', 'Available'),
            ])
            ->orderBy('title')
            ->get()
            ->filter(function (Book $book) use ($request): bool {
                return match ($request->input('availability')) {
                    'Available' => (int) $book->copies_available > 0,
                    'Not Available' => (int) $book->copies_available === 0,
                    default => true,
                };
            })
            ->map(function (Book $book): array {
                $courses = $book->courses->map(fn ($course) => [
                    'course_id' => $course->course_id,
                    'course_name' => $course->course_name,
                    'semester' => $course->semester,
                ])->values()->all();
                $semesters = array_values(array_unique(array_column($courses, 'semester'), SORT_REGULAR));
                $pdf = $book->digitalResources->first(fn ($resource) => $resource->file_path);

                return [
                    'isbn' => $book->isbn,
                    'title' => $book->title,
                    'author' => $book->author,
                    'category' => $book->category,
                    'publisher' => $book->publisher,
                    'publication_year' => $book->publication_year,
                    'edition' => $book->edition,
                    'description' => $book->description,
                    'pic_path' => $book->pic_path,
                    'copies_available' => (int) $book->copies_available,
                    'copies_total' => (int) $book->copies_total,
                    'course_id' => $courses[0]['course_id'] ?? null,
                    'course_ids' => array_column($courses, 'course_id'),
                    'courses' => $courses,
                    'semesters' => $semesters,
                    'pdf_url' => $pdf ? url('/api/books/download_pdf.php?isbn=' . urlencode($book->isbn)) : null,
                ];
            })
            ->values();

        return ['success' => true, 'count' => $books->count(), 'books' => $books];
    }
}