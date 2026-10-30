<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function overview(Request $request)
    {
        abort_unless($request->user()->isStaff(), 403);

        return response()->json([
            'totals' => [
                'members' => User::where('role', 'member')->count(),
                'titles' => Book::count(),
                'checkouts' => Borrowing::where('borrowed_at', '>=', now()->subDays(30))->count(),
                'overdue' => Borrowing::where('status', 'borrowed')->where('due_at', '<', now())->count(),
                'outstanding_fines' => round((float) Fine::whereIn('status', ['unpaid', 'partial'])->sum(DB::raw('amount - paid_amount')), 2),
                'reservations_waiting' => Reservation::where('status', 'requested')->count(),
            ],
            'monthly_activity' => Borrowing::selectRaw("to_char(date_trunc('month', borrowed_at), 'Mon YY') as month, count(*) as total")
                ->where('borrowed_at', '>=', now()->subMonths(5)->startOfMonth())
                ->groupByRaw("date_trunc('month', borrowed_at)")
                ->orderByRaw("date_trunc('month', borrowed_at)")
                ->get(),
            'popular_titles' => Borrowing::select('book_copies.book_id', 'books.title', DB::raw('count(*) as total'))
                ->join('book_copies', 'borrowings.book_copy_id', '=', 'book_copies.id')
                ->join('books', 'book_copies.book_id', '=', 'books.id')
                ->where('borrowings.borrowed_at', '>=', now()->subDays(90))
                ->groupBy('book_copies.book_id', 'books.title')
                ->orderByDesc('total')->limit(8)->get(),
            'overdue_loans' => Borrowing::with(['member:id,name,membership_code', 'copy.book:id,title'])
                ->where('status', 'borrowed')->where('due_at', '<', now())->orderBy('due_at')->limit(50)->get(),
            'recent_audit' => AuditLog::with('user:id,name')->latest()->limit(12)->get(),
        ]);
    }
}
