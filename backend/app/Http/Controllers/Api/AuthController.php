<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'status' => 'active'], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are invalid.']]);
        }

        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Audit::record($request, 'auth.login', $user);

        return response()->json(['user' => $user]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:10'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $user = User::create([
            ...$data,
            'role' => 'member',
            'status' => 'active',
            'membership_code' => 'M-'.Str::upper(Str::random(8)),
        ]);
        Auth::login($user);
        $request->session()->regenerate();
        Audit::record($request, 'member.registered', $user);

        return response()->json(['user' => $user], 201);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    public function logout(Request $request)
    {
        Audit::record($request, 'auth.logout', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
