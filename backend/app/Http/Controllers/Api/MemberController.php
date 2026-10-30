<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MemberRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->withCount([
            'borrowings as active_loans_count' => fn ($borrowings) => $borrowings->where('status', 'borrowed'),
        ]);

        if ($request->user()->role !== 'admin') {
            $query->where('role', 'member');
        }

        $sortBy = in_array($request->string('sort_by')->toString(), ['name', 'email', 'role', 'status', 'created_at'], true)
            ? $request->string('sort_by')->toString() : 'name';
        $direction = $request->string('sort_dir')->toString() === 'desc' ? 'desc' : 'asc';

        return $query
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.trim((string) $request->string('q')).'%';
                $query->where(fn ($sub) => $sub->where('name', 'ilike', $term)
                    ->orWhere('email', 'ilike', $term)
                    ->orWhere('membership_code', 'ilike', $term));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy($sortBy, $direction)->paginate(min(max($request->integer('per_page', 15), 1), 100));
    }

    public function store(MemberRequest $request)
    {
        $data = $request->validated();
        $data['role'] ??= 'member';
        $data['status'] ??= 'active';
        $data['membership_code'] = 'M-'.Str::upper(Str::random(8));
        $member = User::create($data);
        Audit::record($request, 'member.created', $member, ['role' => $member->role]);

        return response()->json($member, 201);
    }

    public function update(MemberRequest $request, User $member)
    {
        abort_if($request->user()->role !== 'admin' && $member->role !== 'member', 403);
        abort_if($member->is($request->user()) && ($request->input('status') === 'inactive' || $request->input('role') !== null), 422, 'You cannot disable or change your own account.');
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $member->update($data);
        Audit::record($request, 'member.updated', $member, ['role' => $member->role, 'status' => $member->status]);

        return $member;
    }

    public function destroy(Request $request, User $member)
    {
        abort_if($request->user()->role !== 'admin' && $member->role !== 'member', 403);
        abort_if($member->is($request->user()), 422, 'You cannot deactivate your own account.');
        $member->update(['status' => 'inactive']);
        Audit::record($request, 'member.deactivated', $member);

        return response()->json(['message' => 'The account has been deactivated.']);
    }
}
