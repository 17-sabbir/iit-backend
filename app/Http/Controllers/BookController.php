<?php

namespace App\Http\Controllers;

use App\Http\Requests\LegacyEndpointRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use App\Models\Book;
use App\Models\BookCopy;
use App\Services\BookCatalogService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BookController extends Controller
{
    public function __construct(private readonly BookCatalogService $catalog) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->catalog->search($request));
    }

    public function categories(): JsonResponse
    {
        $categories = DB::table('Books')->whereNotNull('category')->where('category', '<>', '')
            ->where('title', 'not like', '[DELETED]%')->distinct()->orderBy('category')->pluck('category');
        $semesters = DB::table('Courses')->whereNotNull('semester')->distinct()->orderBy('semester')->pluck('semester');
        return ApiResponse::success(['categories' => $categories, 'semesters' => $semesters]);
    }

    public function copies(LegacyEndpointRequest $request): JsonResponse
    {
        $copies = BookCopy::query()->where('isbn', $request->string('isbn')->toString())
            ->orderBy('copy_id')->get(['copy_id', 'isbn', 'shelf_id', 'compartment_no', 'subcompartment_no', 'status', 'condition_note', 'created_at']);
        return ApiResponse::success(['copies' => $copies, 'count' => $copies->count()]);
    }

    public function courses(LegacyEndpointRequest $request): JsonResponse
    {
        $courses = DB::table('Book_Courses as bc')->join('Courses as c', 'c.course_id', '=', 'bc.course_id')
            ->where('bc.isbn', $request->string('isbn')->toString())
            ->orderBy('c.course_id')->get(['bc.course_id', 'c.course_name', 'c.semester']);
        return ApiResponse::success(['courses' => $courses]);
    }

    public function status(LegacyEndpointRequest $request): JsonResponse
    {
        $isbn = $request->string('isbn')->toString();
        if (!Book::query()->where('isbn', $isbn)->exists()) {
            return ApiResponse::error('Book not found', 404);
        }

        $counts = DB::table('Book_Copies')->where('isbn', $isbn)->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')->pluck('count', 'status');
        $available = (int) ($counts['Available'] ?? 0);
        $borrowed = (int) ($counts['Borrowed'] ?? 0);
        $reserved = DB::table('Reservations')->where('isbn', $isbn)->where('status', 'Active')->count();

        return ApiResponse::success([
            'isbn' => $isbn,
            'copies_total' => (int) $counts->sum(),
            'copies_available' => $available,
            'borrowed_count' => $borrowed,
            'reserved_count' => $reserved,
            'available' => $available > 0,
        ]);
    }

    public function availableCopies(LegacyEndpointRequest $request): JsonResponse
    {
        $copies = DB::table('Book_Copies')->where('isbn', $request->string('isbn')->toString())
            ->where('status', 'Available')->orderBy('copy_id')
            ->get(['copy_id', 'shelf_id', 'compartment_no', 'subcompartment_no', 'condition_note']);
        return ApiResponse::success(['count' => $copies->count(), 'copies' => $copies]);
    }

    public function requestAddition(LegacyEndpointRequest $request): JsonResponse
    {
        $id = DB::table('Requests')->insertGetId([
            'requester_identifier' => $request->user()->email,
            'isbn' => $request->input('isbn'),
            'title' => trim($request->string('title')->toString()),
            'author' => $request->input('author'),
            'category' => $request->input('category'),
            'publisher' => $request->input('publisher'),
            'publication_year' => $request->input('publication_year'),
            'edition' => $request->input('edition'),
            'pdf_path' => $request->input('pdf_path'),
            'pic_path' => $request->input('pic_path'),
            'description' => $request->input('description'),
            'status' => 'Pending',
            'created_at' => now(),
        ]);

        return ApiResponse::success(['request_id' => $id], 201, 'Book request submitted');
    }

    public function additionRequestDetails(LegacyEndpointRequest $request): JsonResponse
    {
        $item = DB::table('Requests')->where('request_id', $request->integer('request_id'))->first([
            'request_id', 'requester_identifier as email', 'isbn', 'title', 'author', 'publisher',
            'publication_year', 'edition', 'pdf_path', 'category', 'pic_path', 'description', 'status', 'approved_by', 'approved_at',
        ]);
        if (!$item) return ApiResponse::error('Request not found', 404);
        if (!in_array($request->user()->role, ['Librarian', 'Director'], true) && $item->email !== $request->user()->email) {
            return ApiResponse::error('Forbidden.', 403);
        }
        return ApiResponse::success(['item' => $item]);
    }

    public function updateAdditionRequest(LegacyEndpointRequest $request): JsonResponse
    {
        $requestId = $request->integer('request_id');
        $item = DB::table('Requests')->where('request_id', $requestId)->first(['request_id', 'requester_identifier', 'status']);
        if (!$item) return ApiResponse::error('Request not found', 404);
        if (!in_array($request->user()->role, ['Librarian', 'Director'], true) && $item->requester_identifier !== $request->user()->email) {
            return ApiResponse::error('Forbidden.', 403);
        }
        if ($item->status !== 'Pending') return ApiResponse::error('Only pending requests can be edited.', 400);

        $fields = ['title', 'author', 'isbn', 'category', 'publisher', 'publication_year', 'edition', 'pdf_path', 'pic_path', 'description'];
        $updates = [];
        foreach ($fields as $field) {
            if ($request->exists($field)) $updates[$field] = $request->input($field);
        }
        if ($updates === []) return ApiResponse::error('No fields to update.', 400);
        DB::table('Requests')->where('request_id', $requestId)->update($updates);
        return ApiResponse::success([], 200, 'Addition request updated successfully');
    }

    public function approveAdditionRequest(LegacyEndpointRequest $request): JsonResponse
    {
        $id = $request->integer('request_id');
        DB::transaction(function () use ($request, $id): void {
            $addition = DB::table('Requests')->where('request_id', $id)->where('status', 'Pending')->lockForUpdate()->first();
            if (!$addition) abort(404, 'Pending request not found or already approved');
            if (!$addition->isbn) abort(400, 'Request must include an ISBN before approval.');

            if (!Book::query()->where('isbn', $addition->isbn)->exists()) {
                DB::table('Books')->insert([
                    'isbn' => $addition->isbn,
                    'title' => $addition->title,
                    'author' => $addition->author ?: 'Unknown',
                    'publisher' => $addition->publisher,
                    'publication_year' => $addition->publication_year,
                    'edition' => $addition->edition,
                    'category' => $addition->category,
                    'pic_path' => $addition->pic_path,
                    'description' => $addition->description,
                ]);
            }
            if ($addition->pdf_path && !DB::table('Digital_Resources')->where('isbn', $addition->isbn)->where('file_path', $addition->pdf_path)->exists()) {
                DB::table('Digital_Resources')->insert([
                    'isbn' => $addition->isbn,
                    'file_name' => basename($addition->pdf_path),
                    'file_path' => $addition->pdf_path,
                    'resource_type' => 'PDF',
                    'uploaded_by' => $addition->requester_identifier,
                ]);
            }
            DB::table('Requests')->where('request_id', $id)->update([
                'status' => 'Approved', 'approved_by' => $request->user()->email, 'approved_at' => now(),
            ]);
            DB::table('Notifications')->insert([
                'user_email' => $addition->requester_identifier,
                'message' => "Your book addition request for '{$addition->title}' has been approved and added to the library collection.",
                'type' => 'AdditionRequestApproved',
                'sent_at' => now(),
            ]);
        });
        return ApiResponse::success([], 200, 'Book addition request approved and book added to library');
    }

    public function declineAdditionRequest(LegacyEndpointRequest $request): JsonResponse
    {
        $id = $request->integer('request_id');
        $addition = DB::table('Requests')->where('request_id', $id)->where('status', 'Pending')->first();
        if (!$addition) return ApiResponse::error('Pending request not found or already processed', 404);
        $reason = trim($request->string('reason')->toString());
        DB::transaction(function () use ($request, $addition, $id, $reason): void {
            $updates = ['status' => 'Rejected', 'approved_by' => $request->user()->email, 'approved_at' => now()];
            if ($reason !== '') $updates['description'] = trim(($addition->description ?? '') . "\nDecline Reason: " . $reason);
            DB::table('Requests')->where('request_id', $id)->update($updates);
            DB::table('Notifications')->insert([
                'user_email' => $addition->requester_identifier,
                'message' => "Your addition request for '{$addition->title}' was declined.",
                'type' => 'System',
                'sent_at' => now(),
            ]);
        });
        return ApiResponse::success([], 200, 'Book addition request declined');
    }

    public function store(LegacyEndpointRequest $request): JsonResponse
    {
        $isbn = trim($request->string('isbn')->toString());
        if (Book::query()->where('isbn', $isbn)->exists()) {
            return ApiResponse::error("A book with ISBN {$isbn} already exists. Please use a different ISBN.", 400);
        }

        $courseIds = $this->courseIds($request->input('course_ids'), $request->input('course_id'));
        $copyIds = $this->stringArray($request->input('copy_ids'));
        $copyLocations = $this->arrayInput($request->input('copy_locations'));
        $copiesTotal = max(0, (int) $request->input('copies_total', count($copyIds)));

        if ($copyIds !== [] && $copiesTotal > 0 && $copiesTotal !== count($copyIds)) {
            return ApiResponse::error('copy_ids count must match copies_total', 400);
        }
        if (count(array_unique($copyIds)) !== count($copyIds)) {
            return ApiResponse::error('Duplicate copy_ids provided', 400);
        }
        if ($courseIds !== [] && DB::table('Courses')->whereIn('course_id', $courseIds)->count() !== count($courseIds)) {
            return ApiResponse::error('One or more courses were not found.', 400);
        }

        $picPath = $request->input('pic_path');
        if ($request->hasFile('image')) {
            $picPath = $this->storeImage($request->file('image'), 'books');
        }

        DB::transaction(function () use ($request, $isbn, $courseIds, $copyIds, $copyLocations, $copiesTotal, $picPath): void {
            DB::table('Books')->insert([
                'isbn' => $isbn,
                'title' => $request->string('title')->toString(),
                'author' => $request->string('author')->toString(),
                'category' => $request->input('category'),
                'publisher' => $request->input('publisher'),
                'publication_year' => $request->input('publication_year'),
                'edition' => $request->input('edition'),
                'description' => $request->input('description'),
                'pic_path' => $picPath,
            ]);

            foreach ($courseIds as $courseId) {
                DB::table('Book_Courses')->insert(['isbn' => $isbn, 'course_id' => $courseId]);
            }

            $copyIdsToInsert = $copyIds;
            if ($copyIdsToInsert === []) {
                for ($index = 1; $index <= $copiesTotal; $index++) {
                    $copyIdsToInsert[] = $isbn . '-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT);
                }
            }

            foreach ($copyIdsToInsert as $index => $copyId) {
                $location = $copyLocations[$index] ?? [];
                DB::table('Book_Copies')->insert([
                    'copy_id' => $copyId,
                    'isbn' => $isbn,
                    'shelf_id' => $location['shelf_id'] ?? $request->input('shelf_id'),
                    'compartment_no' => $location['compartment_no'] ?? $request->input('compartment_no'),
                    'subcompartment_no' => $location['subcompartment_no'] ?? $request->input('subcompartment_no'),
                    'status' => 'Available',
                    'condition_note' => $location['condition_note'] ?? $request->input('condition_note'),
                ]);
            }

            $pdfUrl = trim((string) $request->input('pdf_url', ''));
            if ($pdfUrl !== '') {
                DB::table('Digital_Resources')->insert([
                    'isbn' => $isbn,
                    'file_name' => basename($pdfUrl),
                    'file_path' => $pdfUrl,
                    'resource_type' => 'PDF',
                ]);
            }
        });

        return ApiResponse::success([
            'isbn' => $isbn,
            'copies_created' => $copyIds !== [] ? count($copyIds) : $copiesTotal,
            'course_ids' => $courseIds,
        ], 201, 'Book added successfully');
    }

    public function update(LegacyEndpointRequest $request): JsonResponse
    {
        $isbn = trim($request->string('isbn')->toString());
        $book = Book::query()->find($isbn);
        if (!$book) {
            return ApiResponse::error('Book not found.', 404);
        }

        $allowed = ['title', 'author', 'category', 'publisher', 'publication_year', 'edition', 'description', 'pic_path'];
        $bookUpdates = [];
        foreach ($allowed as $field) {
            if ($request->exists($field)) {
                $bookUpdates[$field] = $request->input($field);
            }
        }

        $hasCourseUpdate = $request->exists('course_id') || $request->exists('course_ids');
        $courseIds = $hasCourseUpdate ? $this->courseIds($request->input('course_ids'), $request->input('course_id')) : [];
        $hasCopiesUpdate = (int) $request->input('copies_total', 0) > 0;
        $hasPdfUpdate = trim((string) $request->input('pdf_url', '')) !== '';

        if ($bookUpdates === [] && !$hasCourseUpdate && !$hasCopiesUpdate && !$hasPdfUpdate) {
            return ApiResponse::error('No fields provided to update', 400);
        }
        if ($courseIds !== [] && DB::table('Courses')->whereIn('course_id', $courseIds)->count() !== count($courseIds)) {
            return ApiResponse::error('One or more courses were not found.', 400);
        }

        DB::transaction(function () use ($request, $book, $bookUpdates, $hasCourseUpdate, $courseIds, $hasCopiesUpdate, $hasPdfUpdate): void {
            if ($bookUpdates !== []) {
                $book->forceFill($bookUpdates)->save();
            }

            if ($hasCourseUpdate && $courseIds !== []) {
                DB::table('Book_Courses')->where('isbn', $book->isbn)->delete();
                foreach ($courseIds as $courseId) {
                    DB::table('Book_Courses')->insert(['isbn' => $book->isbn, 'course_id' => $courseId]);
                }
            }

            if ($hasCopiesUpdate) {
                $copyIds = $this->stringArray($request->input('copy_ids'));
                $locations = $this->arrayInput($request->input('copy_locations'));
                $existing = DB::table('Book_Copies')->where('isbn', $book->isbn)->pluck('copy_id')->all();
                foreach ($copyIds as $index => $copyId) {
                    $location = $locations[$index] ?? [];
                    $copyData = [
                        'shelf_id' => $location['shelf_id'] ?? null,
                        'compartment_no' => $location['compartment_no'] ?? null,
                        'subcompartment_no' => $location['subcompartment_no'] ?? null,
                        'condition_note' => $location['condition_note'] ?? $request->input('condition_note'),
                    ];
                    if (in_array($copyId, $existing, true)) {
                        DB::table('Book_Copies')->where('isbn', $book->isbn)->where('copy_id', $copyId)->update($copyData);
                    } else {
                        DB::table('Book_Copies')->insert($copyData + ['copy_id' => $copyId, 'isbn' => $book->isbn, 'status' => 'Available']);
                    }
                }
            }

            if ($hasPdfUpdate) {
                $pdfUrl = trim((string) $request->input('pdf_url'));
                DB::table('Digital_Resources')->where('isbn', $book->isbn)->where('resource_type', 'PDF')->delete();
                DB::table('Digital_Resources')->insert([
                    'isbn' => $book->isbn,
                    'file_name' => basename($pdfUrl),
                    'file_path' => $pdfUrl,
                    'resource_type' => 'PDF',
                ]);
            }
        });

        return ApiResponse::success([], 200, 'Book updated successfully');
    }

    public function destroy(LegacyEndpointRequest $request): JsonResponse
    {
        $isbn = trim($request->string('isbn')->toString());
        $updated = DB::transaction(function () use ($isbn): int {
            $updated = DB::table('Books')->where('isbn', $isbn)->update([
                'pic_path' => DB::raw('CONCAT("[DELETED]", COALESCE(pic_path, ""))'),
                'title' => DB::raw('CONCAT("[DELETED] ", title)'),
            ]);
            if ($updated > 0) {
                DB::table('Book_Copies')->where('isbn', $isbn)->update(['status' => 'Discarded']);
            }
            return $updated;
        });

        return $updated > 0
            ? ApiResponse::success([], 200, 'Book removed successfully')
            : ApiResponse::error('Book not found', 404);
    }

    public function uploadRequestPdf(LegacyEndpointRequest $request): JsonResponse
    {
        $file = $request->file('pdf');
        $filename = $this->storePdf($file, true);
        if (!$filename) return ApiResponse::error('Failed to save uploaded file.', 500);
        return ApiResponse::success([
            'path' => 'uploads/pdfs/' . $filename,
            'filename' => $filename,
            'size' => $file->getSize(),
        ], 200, 'PDF uploaded successfully');
    }

    public function uploadPdf(LegacyEndpointRequest $request): JsonResponse
    {
        $filename = $this->storePdf($request->file('pdf'), false);
        if (!$filename) return ApiResponse::error('Failed to save uploaded file.', 500);
        $path = '/uploads/pdfs/' . $filename;
        return ApiResponse::success(['url' => $path, 'path' => $path]);
    }

    public function uploadCoverImage(LegacyEndpointRequest $request): JsonResponse
    {
        $path = $this->storeImage($request->file('image'), 'covers');
        if (!$path) return ApiResponse::error('Failed to save uploaded file.', 500);
        return ApiResponse::success(['path' => $path], 200, 'Cover image uploaded successfully');
    }

    public function updateCoverImage(LegacyEndpointRequest $request): JsonResponse
    {
        $book = Book::query()->find($request->string('isbn')->toString());
        if (!$book) return ApiResponse::error('Book not found.', 404);
        $path = $this->storeImage($request->file('image'), 'books');
        if (!$path) return ApiResponse::error('Failed to save image.', 500);
        $book->forceFill(['pic_path' => $path])->save();
        return ApiResponse::success([
            'image_url' => url('/api/serve_image.php?path=' . urlencode($path)),
        ], 200, 'Book cover updated successfully.');
    }

    public function downloadPdf(LegacyEndpointRequest $request): \Symfony\Component\HttpFoundation\Response|JsonResponse
    {
        $isbn = $request->string('isbn')->toString();
        $path = DB::table('Digital_Resources')->where('isbn', $isbn)->where('resource_type', 'PDF')->value('file_path');
        if (!$path && Schema::hasColumn('Books', 'pdf_url')) {
            $path = DB::table('Books')->where('isbn', $isbn)->value('pdf_url');
        }
        if (!$path) return ApiResponse::error('No PDF found for this book.', 404);

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            if (!in_array(parse_url($path, PHP_URL_SCHEME), ['http', 'https'], true)) {
                return ApiResponse::error('Invalid PDF URL.', 400);
            }
            return redirect()->away($path);
        }

        $uploads = realpath(base_path('uploads'));
        $candidate = realpath(base_path(ltrim((string) $path, '/')));
        if (!$uploads || !$candidate || !str_starts_with($candidate, $uploads . DIRECTORY_SEPARATOR) || !is_file($candidate)) {
            return ApiResponse::error('PDF file not found on server.', 404);
        }

        return response()->file($candidate, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="book_' . rawurlencode($isbn) . '.pdf"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function courseIds(mixed $courseIds, mixed $courseId): array
    {
        $values = is_array($courseIds) ? $courseIds : [];
        $values = array_merge($values, is_array($courseId) ? $courseId : [$courseId]);
        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $values,
        ), static fn ($value) => $value !== '' && $value !== 'NONE')));
    }

    private function stringArray(mixed $values): array
    {
        if (is_string($values)) {
            $values = json_decode($values, true) ?: [];
        }
        return is_array($values) ? array_values(array_filter(array_map(static fn ($value) => trim((string) $value), $values))) : [];
    }

    private function arrayInput(mixed $values): array
    {
        if (is_string($values)) {
            $values = json_decode($values, true) ?: [];
        }
        return is_array($values) ? $values : [];
    }

    private function storeImage(?\Illuminate\Http\UploadedFile $image, string $folder): ?string
    {
        if (!$image) {
            return null;
        }
        $extension = match ($image->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };
        if (!$extension) {
            return null;
        }

        $directory = base_path('uploads/' . $folder);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $image->move($directory, $filename);
        return 'uploads/' . $folder . '/' . $filename;
    }

    private function storePdf(?UploadedFile $file, bool $readableName): ?string
    {
        if (!$file || $file->getMimeType() !== 'application/pdf') return null;
        $directory = base_path('uploads/pdfs');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) return null;
        $filename = $readableName
            ? preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '_' . now()->timestamp . '_' . bin2hex(random_bytes(6)) . '.pdf'
            : 'pdf_' . now()->timestamp . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $file->move($directory, $filename);
        return $filename;
    }

    public function show(Book $book): JsonResponse
    {
        return ApiResponse::success(['book' => $book->load('copies', 'courses')]);
    }
}