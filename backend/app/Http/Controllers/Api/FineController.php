<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use App\Models\Payment;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FineController extends Controller
{
    public function index(Request $request)
    {
        $query = Fine::with(['member:id,name,email,membership_code', 'borrowing.copy.book:id,title', 'payments']);
        if (! $request->user()->isStaff()) {
            $query->where('user_id', $request->user()->id);
        }
        return $query->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()->paginate(min(max($request->integer('per_page', 15), 1), 100));
    }

    public function pay(Request $request, Fine $fine)
    {
        abort_unless($request->user()->isStaff(), 403, 'Payments must be recorded by a librarian.');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(['cash', 'card', 'transfer', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        $payment = DB::transaction(function () use ($fine, $request, $data): Payment {
            $fine = Fine::query()->lockForUpdate()->findOrFail($fine->id);
            $remaining = round((float) $fine->amount - (float) $fine->paid_amount, 2);
            abort_if($data['amount'] > $remaining, 422, 'Payment cannot exceed the outstanding balance.');
            $payment = Payment::create([
                'fine_id' => $fine->id,
                'received_by' => $request->user()->isStaff() ? $request->user()->id : null,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_at' => now(),
            ]);
            $paid = round((float) $fine->paid_amount + (float) $payment->amount, 2);
            $fine->update(['paid_amount' => $paid, 'status' => $paid >= (float) $fine->amount ? 'paid' : 'partial']);
            return $payment;
        });
        Audit::record($request, 'fine.payment_received', $fine, ['payment_id' => $payment->id, 'amount' => $payment->amount]);
        return response()->json(['payment' => $payment, 'fine' => $fine->fresh()], 201);
    }

    public function waive(Request $request, Fine $fine)
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless($fine->status !== 'paid', 422, 'A paid fine cannot be waived.');
        $fine->update(['status' => 'waived', 'notes' => $request->input('reason', 'Waived by administrator')]);
        Audit::record($request, 'fine.waived', $fine, ['reason' => $fine->notes]);
        return $fine;
    }
}
