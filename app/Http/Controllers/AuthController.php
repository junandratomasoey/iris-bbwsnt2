<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    /** Login dengan username atau nomor HP + password. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string'],
        ], [], ['username' => 'username / no. HP']);

        $login = trim($data['username']);
        $hp = normalisasi_hp($login);
        $user = User::where('username', $login)
            ->when($hp && strlen($hp) >= 9, fn ($q) => $q->orWhere('no_hp', $hp)->orWhere('username', $hp))
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['username' => 'Username/no. HP atau password salah.'])->onlyInput('username');
        }
        if (! $user->is_active) {
            return back()->withErrors(['username' => 'Akun Anda dinonaktifkan. Hubungi Admin OP.'])->onlyInput('username');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beranda');
    }

    public function editPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password_lama' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers(), 'different:password_lama'],
        ], [], ['password_lama' => 'password lama', 'password' => 'password baru']);

        $request->user()->update(['password' => $request->password, 'must_change_password' => false]);

        return redirect()->route('dashboard')->with('ok', 'Password berhasil diganti.');
    }
}
