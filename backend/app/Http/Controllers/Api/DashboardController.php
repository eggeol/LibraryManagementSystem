<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $member = ! $user->isStaff();
        $activeLoans = Borrowing::query()->where('status', 'borrowed');

        if ($member) {
            $activeLoans->where('user_id', $user->id);
        }

        return response()->json([
            'stats' => [
                'titles' => Book::count(),
                'copies' => BookCopy::count(),
                'available_copies' => BookCopy::where('status', 'available')->count(),
                'members' => User::where('role', 'member')->where('status', 'active')->count(),
                'active_loans' => (clone $activeLoans)->count(),
                'overdue_loans' => (clone $activeLoans)->where('due_at', '<', now())->count(),
                'pending_reservations' => Reservation::whereIn('status', ['requested', 'ready'])->count(),
                'unpaid_fines' => $member
                    ? Fine::where('user_id', $user->id)->whereIn('status', ['unpaid', 'partial'])->sum('amount')
                        - Fine::where('user_id', $user->id)->sum('paid_amount')
                    : Fine::whereIn('status', ['unpaid', 'partial'])->sum('amount')
                        - Fine::whereIn('status', ['unpaid', 'partial'])->sum('paid_amount'),
            ],
            'recent_loans' => (clone $activeLoans)
                ->with(['copy.book:id,title', 'member:id,name,membership_code'])
                ->latest('borrowed_at')->limit(6)->get(),
            'recent_activity' => $member ? [] : \App\Models\AuditLog::with('user:id,name')
                ->latest()->limit(8)->get(),
        ]);
    }
}
