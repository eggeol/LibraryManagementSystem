<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        return AuditLog::with('user:id,name,email')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->string('q')).'%';
                $query->where(fn ($sub) => $sub->where('action', 'ilike', $term)->orWhere('entity_type', 'ilike', $term));
            })
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->latest()->paginate(min(max($request->integer('per_page', 25), 1), 100));
    }
}
