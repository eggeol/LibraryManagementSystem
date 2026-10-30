<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Fine;
use App\Models\LibrarySetting;
use App\Models\Reservation;
use App\Models\User;
use App\Notifications\LibraryNotification;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CirculationController extends Controller
{
    public function borrowings(Request $request)
    {
        $query = Borrowing::with(['copy.book.authors:id,name', 'member:id,name,email,membership_code']);
        if (! $request->user()->isStaff()) {
            $query->where('user_id', $request->user()->id);
        }
        return $query->when($request->filled('status'), function ($query) use ($request): void {
            $status = (string) $request->string('status');
            $status === 'overdue'
                ? $query->where('status', 'borrowed')->where('due_at', '<', now())
                : $query->where('status', $status);
        })->latest('borrowed_at')->paginate(min(max($request->integer('per_page', 15), 1), 100));
    }

    public function issue(Request $request)
    {
        $this->expireReadyReservations();
        $data = $request->validate([
            'book_copy_id' => ['required', 'integer', 'exists:book_copies,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);
        $borrowing = DB::transaction(function () use ($request, $data): Borrowing {
            $copy = BookCopy::query()->lockForUpdate()->findOrFail($data['book_copy_id']);
            $member = User::query()->lockForUpdate()->findOrFail($data['user_id']);
            abort_unless($member->role === 'member' && $member->status === 'active', 422, 'Choose an active library member.');
            abort_unless(in_array($copy->status, ['available', 'reserved'], true), 422, 'This copy is not available to borrow.');
            $readyReservation = Reservation::where('book_copy_id', $copy->id)->where('status', 'ready')->lockForUpdate()->first();
            abort_if($copy->status === 'reserved' && (! $readyReservation || $readyReservation->user_id !== $member->id), 422, 'This copy is held for another member.');
            $activeCount = Borrowing::where('user_id', $member->id)->where('status', 'borrowed')->count();
            abort_if($activeCount >= (int) $this->setting('max_active_loans', 5), 422, 'This member has reached the active-loan limit.');
            abort_if(Borrowing::where('user_id', $member->id)->where('book_copy_id', $copy->id)->where('status', 'borrowed')->exists(), 422, 'This copy is already checked out.');

            $loan = Borrowing::create([
                'book_copy_id' => $copy->id,
                'user_id' => $member->id,
                'issued_by' => $request->user()->id,
                'borrowed_at' => now(),
                'due_at' => $data['due_at'] ?? now()->addDays((int) $this->setting('loan_days', 14)),
                'status' => 'borrowed',
            ]);
            $copy->update(['status' => 'borrowed']);
            $readyReservation?->update(['status' => 'fulfilled', 'expires_at' => null]);
            return $loan;
        });
        $borrowing->member->notify(new LibraryNotification('Book checked out', $borrowing->copy->book->title.' is due '.$borrowing->due_at->format('M j, Y').'.', ['borrowing_id' => $borrowing->id]));
        Audit::record($request, 'borrowing.issued', $borrowing, ['member_id' => $borrowing->user_id, 'copy_id' => $borrowing->book_copy_id]);
        return response()->json($borrowing->load(['copy.book', 'member']), 201);
    }

    public function returnBook(Request $request, Borrowing $borrowing)
    {
        abort_unless($borrowing->status === 'borrowed', 422, 'This loan has already been closed.');
        $result = DB::transaction(function () use ($borrowing, $request): array {
            $loan = Borrowing::query()->lockForUpdate()->findOrFail($borrowing->id);
            $copy = BookCopy::query()->lockForUpdate()->findOrFail($loan->book_copy_id);
            $loan->update(['status' => 'returned', 'returned_at' => now(), 'returned_to' => $request->user()->id]);
            $lateDays = max(0, (int) $loan->due_at->copy()->startOfDay()->diffInDays(now()->startOfDay(), false));
            $fine = null;
            if ($lateDays > 0) {
                $amount = round($lateDays * (float) $this->setting('fine_per_day', 0.5), 2);
                if ($amount > 0) {
                    $fine = Fine::firstOrCreate(
                        ['borrowing_id' => $loan->id],
                        ['user_id' => $loan->user_id, 'amount' => $amount, 'paid_amount' => 0, 'status' => 'unpaid'],
                    );
                }
            }
            $next = $this->allocateNextReservation($copy);
            if (! $next) {
                $copy->update(['status' => 'available']);
            }
            return [$loan->fresh(['copy.book', 'member']), $fine, $next];
        });
        [$loan, $fine, $next] = $result;
        $loan->member->notify(new LibraryNotification('Book returned', $loan->copy->book->title.' was checked in.'.($fine ? ' An overdue fine was added to your account.' : ''), ['borrowing_id' => $loan->id]));
        Audit::record($request, 'borrowing.returned', $loan, ['fine_id' => $fine?->id]);
        return response()->json(['borrowing' => $loan, 'fine' => $fine, 'reservation' => $next]);
    }

    public function renew(Request $request, Borrowing $borrowing)
    {
        abort_if(! $request->user()->isStaff() && $borrowing->user_id !== $request->user()->id, 403);
        abort_unless($borrowing->status === 'borrowed', 422, 'This loan cannot be renewed.');
        abort_if($borrowing->due_at->isPast(), 422, 'Overdue books must be returned before they can be renewed.');
        abort_if(Reservation::where('book_id', $borrowing->copy->book_id)->where('status', 'requested')->exists(), 422, 'Another member is waiting for this title.');
        $days = (int) $this->setting('loan_days', 14);
        $borrowing->update(['due_at' => $borrowing->due_at->copy()->addDays($days)]);
        Audit::record($request, 'borrowing.renewed', $borrowing, ['due_at' => $borrowing->due_at->toIso8601String()]);
        return $borrowing->fresh(['copy.book', 'member']);
    }

    public function reservations(Request $request)
    {
        $this->expireReadyReservations();
        $query = Reservation::with(['book:id,title,isbn', 'copy:id,accession_number,shelf_location', 'member:id,name,email,membership_code']);
        if (! $request->user()->isStaff()) {
            $query->where('user_id', $request->user()->id);
        }
        return $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('reserved_at')->paginate(min(max($request->integer('per_page', 15), 1), 100));
    }

    public function reserve(Request $request, Book $book)
    {
        $user = $request->user();
        abort_unless($user->role === 'member' && $user->status === 'active', 403);
        abort_if(Reservation::where('user_id', $user->id)->where('book_id', $book->id)->whereIn('status', ['requested', 'ready'])->exists(), 422, 'You already have an active reservation for this title.');
        abort_if(Borrowing::where('user_id', $user->id)->where('status', 'borrowed')->whereHas('copy', fn ($q) => $q->where('book_id', $book->id))->exists(), 422, 'You already have this title checked out.');
        $reservation = DB::transaction(function () use ($book, $user): Reservation {
            $reservation = Reservation::create([
                'book_id' => $book->id,
                'user_id' => $user->id,
                'reserved_at' => now(),
                'status' => 'requested',
            ]);
            $copy = BookCopy::where('book_id', $book->id)->where('status', 'available')->lockForUpdate()->first();
            if ($copy) {
                $reservation->update([
                    'book_copy_id' => $copy->id,
                    'status' => 'ready',
                    'expires_at' => now()->addHours((int) $this->setting('reservation_hold_hours', 48)),
                ]);
                $copy->update(['status' => 'reserved']);
            }
            return $reservation;
        });
        Audit::record($request, 'reservation.created', $reservation, ['book_id' => $book->id]);
        return response()->json($reservation->load(['book', 'copy']), 201);
    }

    public function cancelReservation(Request $request, Reservation $reservation)
    {
        abort_unless(in_array($reservation->status, ['requested', 'ready'], true), 422, 'This reservation is already closed.');
        abort_if(! $request->user()->isStaff() && $reservation->user_id !== $request->user()->id, 403);
        DB::transaction(function () use ($reservation): void {
            $reservation->update(['status' => 'cancelled']);
            if ($reservation->book_copy_id) {
                $copy = BookCopy::query()->lockForUpdate()->find($reservation->book_copy_id);
                if ($copy && $copy->status === 'reserved') {
                    $next = $this->allocateNextReservation($copy);
                    if (! $next) {
                        $copy->update(['status' => 'available']);
                    }
                }
            }
        });
        Audit::record($request, 'reservation.cancelled', $reservation);
        return response()->noContent();
    }

    private function allocateNextReservation(BookCopy $copy): ?Reservation
    {
        $copy->update(['status' => 'available']);
        $next = Reservation::where('book_id', $copy->book_id)->where('status', 'requested')->orderBy('reserved_at')->lockForUpdate()->first();
        if (! $next) {
            return null;
        }
        $next->update([
            'book_copy_id' => $copy->id,
            'status' => 'ready',
            'expires_at' => now()->addHours((int) $this->setting('reservation_hold_hours', 48)),
        ]);
        $copy->update(['status' => 'reserved']);
        $next->member->notify(new LibraryNotification('Reserved book ready', $copy->book->title.' is being held for you. Please collect it before '.$next->expires_at->format('M j, Y g:i A').'.', ['reservation_id' => $next->id]));
        return $next;
    }

    private function expireReadyReservations(): void
    {
        DB::transaction(function (): void {
            $expired = Reservation::where('status', 'ready')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($expired as $reservation) {
                $reservation->update(['status' => 'expired']);
                if (! $reservation->book_copy_id) {
                    continue;
                }
                $copy = BookCopy::query()->lockForUpdate()->find($reservation->book_copy_id);
                if (! $copy || $copy->status !== 'reserved') {
                    continue;
                }
                if (! $this->allocateNextReservation($copy)) {
                    $copy->update(['status' => 'available']);
                }
            }
        });
    }

    private function setting(string $key, mixed $default): mixed
    {
        $value = LibrarySetting::where('key', $key)->first()?->value;
        return $value === null ? $default : (is_array($value) ? ($value['value'] ?? $default) : $value);
    }
}
