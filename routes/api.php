<?php

use App\Models\User;
use Illuminate\Http\Request; // <-- নিশ্চিত করুন এখানে কোনো { বা বাড়তি চিহ্ন নেই
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::post('/login', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $token = $user->createToken('mobile-app')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'username' => $user->username ?? $user->email,
        ],
    ]);
});

Broadcast::routes(['middleware' => ['auth:sanctum']]);