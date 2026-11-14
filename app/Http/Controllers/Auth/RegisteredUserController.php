<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Carteira\CriarCarteira;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class RegisteredUserController extends Controller
{
    /** @return \Illuminate\View\View */
    public function create()
    {
        return view('auth.register');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, CriarCarteira $criarCarteira)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/\p{C}/u'],

            'email' => ['required', 'string', 'email', 'not_regex:/[\r\n]/', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        try {
            $user = DB::transaction(function () use ($request, $criarCarteira) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);

                $criarCarteira->handle($user);

                return $user;
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }

            throw ValidationException::withMessages(['email' => __('validation.custom.email.unique')]);
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
