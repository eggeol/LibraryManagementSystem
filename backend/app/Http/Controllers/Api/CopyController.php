<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCopy;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CopyController extends Controller
{
    public function index(Request $request)
    {
        $sortBy = in_array($request->string('sort_by')->toString(), ['accession_number', 'status', 'shelf_location', 'created_at'], true)
            ? $request->string('sort_by')->toString() : 'created_at';
        $direction = $request->string('sort_dir')->toString() === 'desc' ? 'desc' : 'asc';
        return BookCopy::with('book:id,title,isbn')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->string('q')).'%';
                $query->where(fn ($sub) => $sub->where('accession_number', 'ilike', $term)
                    ->orWhereHas('book', fn ($books) => $books->where('title', 'ilike', $term)));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('book_id'), fn ($query) => $query->where('book_id', $request->integer('book_id')))
            ->orderBy($sortBy, $direction)->paginate(min(max($request->integer('per_page', 15), 1), 100));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'accession_number' => ['required', 'string', 'max:64', 'unique:book_copies,accession_number'],
            'barcode' => ['nullable', 'string', 'max:64', 'unique:book_copies,barcode'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);
        $copy = BookCopy::create([...$data, 'status' => 'available']);
        Audit::record($request, 'copy.created', $copy, ['book_id' => $copy->book_id]);

        return response()->json($copy->load('book:id,title,isbn'), 201);
    }

    public function update(Request $request, BookCopy $copy)
    {
        abort_unless($copy->status !== 'borrowed' && $copy->status !== 'reserved', 422, 'A borrowed or reserved copy cannot be edited.');
        $data = $request->validate([
            'accession_number' => ['sometimes', 'required', 'string', 'max:64', Rule::unique('book_copies', 'accession_number')->ignore($copy->id)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('book_copies', 'barcode')->ignore($copy->id)],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['available', 'damaged', 'lost'])],
        ]);
        $copy->update($data);
        Audit::record($request, 'copy.updated', $copy, ['status' => $copy->status]);

        return $copy->load('book:id,title,isbn');
    }

    public function destroy(Request $request, BookCopy $copy)
    {
        abort_unless(in_array($copy->status, ['available', 'damaged', 'lost'], true), 422, 'A borrowed or reserved copy cannot be removed.');
        abort_if($copy->borrowings()->exists(), 422, 'Copies with circulation history cannot be removed; mark them lost instead.');
        Audit::record($request, 'copy.deleted', $copy);
        $copy->delete();

        return response()->noContent();
    }
}
