<?php

namespace App\Http\Controllers;

use App\Events\UserRegistered;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Register a new user and provision their wallet atomically.
     *
     * DB::transaction ensures that a User is NEVER created without a Wallet,
     * and a Wallet is NEVER created without a User — they succeed or fail together.
     *
     * After the transaction commits, the UserRegistered event is dispatched.
     * The SendWelcomeEmail listener reacts to it independently; the controller
     * does not care how (or whether) the email is sent.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);

            Wallet::create([
                'user_id'  => $user->id,
                'balance'  => 0.00,
                'currency' => strtoupper($request->input('currency', 'USD')),
            ]);

            return $user;
        });

        // Fired AFTER the transaction commits — listener won't run if the
        // transaction rolled back, preventing "welcome email" for users that
        // were never actually saved.
        UserRegistered::dispatch($user);

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'wallet' => $user->load('wallets')->wallets->first(),
            ],
        ], 201);
    }
}
