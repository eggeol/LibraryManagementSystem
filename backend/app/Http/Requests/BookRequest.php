<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        $book = $this->route('book');

        return [
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20', Rule::unique('books', 'isbn')->ignore($book?->id)],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'edition' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'published_year' => ['nullable', 'integer', 'between:1400,'.(now()->year + 1)],
            'language' => ['required', 'string', 'max:8'],
            'cover_url' => ['nullable', 'url', 'max:2048'],
            'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
            'author_ids' => ['sometimes', 'array'],
            'author_ids.*' => ['integer', 'distinct', 'exists:authors,id'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }
}
