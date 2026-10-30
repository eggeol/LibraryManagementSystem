<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibrarySetting;
use App\Support\Audit;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    private const DEFAULTS = [
        'library_name' => 'LNU SmartLib',
        'loan_days' => 14,
        'fine_per_day' => 0.5,
        'reservation_hold_hours' => 48,
        'max_active_loans' => 5,
    ];

    public function index()
    {
        $stored = LibrarySetting::all()->mapWithKeys(fn ($setting) => [
            $setting->key => is_array($setting->value) ? ($setting->value['value'] ?? $setting->value) : $setting->value,
        ]);
        return response()->json([...self::DEFAULTS, ...$stored->all()]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'library_name' => ['sometimes', 'required', 'string', 'max:120'],
            'loan_days' => ['sometimes', 'required', 'integer', 'between:1,90'],
            'fine_per_day' => ['sometimes', 'required', 'numeric', 'between:0,100'],
            'reservation_hold_hours' => ['sometimes', 'required', 'integer', 'between:1,336'],
            'max_active_loans' => ['sometimes', 'required', 'integer', 'between:1,50'],
        ]);
        foreach ($data as $key => $value) {
            LibrarySetting::updateOrCreate(['key' => $key], ['value' => ['value' => $value]]);
        }
        Audit::record($request, 'settings.updated', null, $data);
        return $this->index();
    }
}
