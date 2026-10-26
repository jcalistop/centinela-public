<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthTokenController extends Controller
{
    /**
     * Endpoint para emitir token Sanctum para admin
     */
    public function issueToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::query()->where('email', $request->email)->first();
        $isAdmin = $user instanceof User && $user->hasRole('admin');
        $passwordOk = $isAdmin && Hash::check((string) $request->password, $user->password);

        if (! $isAdmin || ! $passwordOk) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        $ttlMinutes = config('centinela.admin_api_token.ttl_minutes');
        $expiresAt = is_int($ttlMinutes) && $ttlMinutes > 0
            ? now()->addMinutes($ttlMinutes)
            : null;

        $token = $user->createToken('admin-api-token', ['*'], $expiresAt)->plainTextToken;
        Log::info('Token Sanctum emitido para admin', ['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ]);
    }
}
