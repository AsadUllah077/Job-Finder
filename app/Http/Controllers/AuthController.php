<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function form(string $mode = 'login')
    {
        return view('auth.form', compact('mode'));
    }

    public function register(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:254|unique:users', 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()]]);
        $user = User::create($data);
        $user->preference()->create(['preferred_keywords' => config('jobfinder.default_keywords'), 'preferred_locations' => Setting::read('locations', config('jobfinder.default_locations')), 'alert_frequency' => Setting::read('alert_frequency', 'daily')]);
        Auth::login($user);
        $r->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string', 'remember' => 'nullable|boolean']);
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']], $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        $r->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        if (in_array(config('mail.default'), ['log', 'array'])) {
            return back()->with('status', 'Password reset mail is not configured. Contact the administrator to configure SMTP.');
        }
        Password::sendResetLink($r->only('email'));

        return back()->with('status', 'If that account exists, a reset link has been requested.');
    }

    public function resetForm(Request $r, string $token)
    {
        return view('auth.form', ['mode' => 'reset', 'token' => $token, 'email' => $r->query('email')]);
    }

    public function reset(Request $r)
    {
        $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()]]);
        $status = Password::reset($r->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', __($status));
    }
}
