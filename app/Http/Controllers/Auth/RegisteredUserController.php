<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'categories' => collect(\App\Support\Roles::SELF_SERVE)->map(fn ($k) => ['value' => $k, 'label' => \App\Support\Roles::CATEGORIES[$k]])->values(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'username' => ['required', 'string', 'min:3', 'max:40', 'alpha_dash', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'category' => ['required', 'in:'.implode(',', \App\Support\Roles::SELF_SERVE)],
            'graduation_year' => ['nullable', 'integer', 'min:1929', 'max:'.(now()->year + 7)],
            'current_class' => ['nullable', 'string', 'max:40'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => strtolower($request->username),
            'password' => Hash::make($request->password),
            'category' => $request->category,
            'graduation_year' => $request->graduation_year,
            'current_class' => $request->category === 'student' ? $request->current_class : null,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
