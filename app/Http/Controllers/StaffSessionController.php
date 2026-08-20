<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Login minimo: un account, nessun ruolo, nessuna registrazione.
 * Il "ricordami" e' sempre attivo perche' il dispositivo dietro al banco
 * e' fisso e controllato: il macellaio non deve rifare il login durante
 * la giornata (§37).
 */
class StaffSessionController extends Controller
{
    public function create(): View
    {
        return view('staff-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, remember: true)) {
            return back()
                ->withErrors(['email' => 'Email o password non corretti.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('staff.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
