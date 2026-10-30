<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Support\Audit;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request)
    {
        $sortBy = in_array($request->string('sort_by')->toString(), ['title', 'isbn', 'published_year', 'created_at'], true)
            ? $request->string('sort_by')->toString() : 'title';
        $direction = $request->string('sort_dir')->toString() === 'desc' ? 'desc' : 'asc';
        $books = Book::query()
            ->with(['authors:id,name', 'publisher:id,name', 'categories:id,name'])
            ->withCount([
                'copies',
                'copies as available_copies_count' => fn ($query) => $query->where('status', 'available'),
            ])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = trim($request->string('q'));
                $query->where(function ($query) use ($term): void {
                    $query->where('title', 'ilike', "%{$term}%")
                        ->orWhere('isbn', 'ilike', "%{$term}%")
                        ->orWhereHas('authors', fn ($authors) => $authors->where('name', 'ilike', "%{$term}%"));
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas('categories', fn ($categories) => $categories->whereKey($request->integer('category_id'))))
            ->when($request->boolean('available'), fn ($query) => $query->whereHas('copies', fn ($copies) => $copies->where('status', 'available')))
            ->orderBy($sortBy, $direction);

        return $books->paginate(min(max($request->integer('per_page', 12), 1), 100));
    }

    public function show(Book $book)
    {
        return $book->load(['authors:id,name', 'publisher:id,name', 'categories:id,name', 'copies'])
            ->loadCount(['copies', 'copies as available_copies_count' => fn ($query) => $query->where('status', 'available')]);
    }

    public function store(BookRequest $request)
    {
        $data = $request->validated();
        $authorIds = $data['author_ids'] ?? [];
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['author_ids'], $data['category_ids']);
        $book = Book::create([...$data, 'created_by' => $request->user()->id]);
        $book->authors()->sync($authorIds);
        $book->categories()->sync($categoryIds);
        Audit::record($request, 'book.created', $book, ['title' => $book->title]);

        return response()->json($book->load(['authors', 'publisher', 'categories']), 201);
    }

    public function update(BookRequest $request, Book $book)
    {
        $data = $request->validated();
        $book->authors()->sync($data['author_ids'] ?? []);
        $book->categories()->sync($data['category_ids'] ?? []);
        unset($data['author_ids'], $data['category_ids']);
        $book->update($data);
        Audit::record($request, 'book.updated', $book, ['title' => $book->title]);

        return $book->load(['authors', 'publisher', 'categories']);
    }

    public function destroy(Request $request, Book $book)
    {
        abort_if($book->copies()->exists(), 422, 'Remove or archive the physical copies before deleting this title.');
        Audit::record($request, 'book.deleted', $book, ['title' => $book->title]);
        $book->delete();

        return response()->noContent();
    }
}
