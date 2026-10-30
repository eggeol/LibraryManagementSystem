<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Category;
use App\Models\Publisher;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LookupController extends Controller
{
    private function model(string $type): string
    {
        return match ($type) {
            'authors' => Author::class,
            'publishers' => Publisher::class,
            'categories' => Category::class,
            default => abort(404),
        };
    }

    public function index(Request $request, string $type)
    {
        $model = $this->model($type);

        $direction = $request->string('sort_dir')->toString() === 'desc' ? 'desc' : 'asc';
        return $model::query()
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'ilike', '%'.$request->string('q').'%'))
            ->orderBy('name', $direction)->paginate(min(max($request->integer('per_page', 50), 1), 100));
    }

    public function store(Request $request, string $type)
    {
        $model = $this->model($type);
        $rules = ['name' => ['required', 'string', 'max:255', Rule::unique((new $model)->getTable(), 'name')]];
        if ($type === 'authors') {
            $rules['biography'] = ['nullable', 'string'];
        } elseif ($type === 'publishers') {
            $rules['email'] = ['nullable', 'email', 'max:255'];
            $rules['website'] = ['nullable', 'url', 'max:2048'];
        } else {
            $rules['description'] = ['nullable', 'string', 'max:255'];
        }
        $entry = $model::create($request->validate($rules));
        Audit::record($request, $type.'.created', $entry);

        return response()->json($entry, 201);
    }

    public function update(Request $request, string $type, int $id)
    {
        $model = $this->model($type);
        $entry = $model::findOrFail($id);
        $rules = ['name' => ['required', 'string', 'max:255', Rule::unique($entry->getTable(), 'name')->ignore($entry->id)]];
        if ($type === 'authors') {
            $rules['biography'] = ['nullable', 'string'];
        } elseif ($type === 'publishers') {
            $rules['email'] = ['nullable', 'email', 'max:255'];
            $rules['website'] = ['nullable', 'url', 'max:2048'];
        } else {
            $rules['description'] = ['nullable', 'string', 'max:255'];
        }
        $entry->update($request->validate($rules));
        Audit::record($request, $type.'.updated', $entry);

        return $entry;
    }

    public function destroy(Request $request, string $type, int $id)
    {
        $model = $this->model($type);
        $entry = $model::findOrFail($id);
        abort_if($entry->books()->exists(), 422, 'This record is linked to one or more books.');
        Audit::record($request, $type.'.deleted', $entry);
        $entry->delete();

        return response()->noContent();
    }
}
