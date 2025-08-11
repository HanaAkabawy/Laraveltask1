<?php

namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    
    public function showRegister()
    {
        return view('auth.register');
    }

    
    public function apiRegister(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Email verification JWT (1 hour)
        $secret = (string) env('JWT_SECRET');
        $token = JWT::encode([
            'email' => $user->email,
            'exp' => time() + 3600,
        ], $secret, 'HS256');

        return response()->json([
            'msg' => 'User registered.',
            'verificationToken' => $token,
        ]);
    }

    public function apiLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'msg' => 'Login failed. Make sure your email and password are correct',
            ], 401);
        }

        $secret = (string) env('JWT_SECRET');
        $token = JWT::encode([
            'userId' => $user->id,
            'email' => $user->email,
            'exp' => time() + 86400,
        ], $secret, 'HS256');

        return response()->json(['msg' => 'Logged in successfully', 'token' => $token]);
    }



    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user) {
            return response()->json(['msg' => 'User not found'], 404);
        }

        $secret = (string) env('JWT_SECRET');
        $token = JWT::encode([
            'email' => $user->email,
            'exp' => time() + 900, // 15 minutes
        ], $secret, 'HS256');

        $backendUrl = rtrim((string) env('BACKEND_URL', config('app.url')), '/');
        $resetEndpoint = $backendUrl . '/api/auth/reset-password';

        // Send email with reset link
        try {
            Mail::send('emails.forgot-password', [
                'user' => $user,
                'token' => $token,
                'resetEndpoint' => $resetEndpoint
            ], function ($message) use ($user) {
                $message->to($user->email)
                        ->subject('Password Reset Request');
            });

            return response()->json([
                'msg' => 'Password reset email sent successfully. Please check your email.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'msg' => 'Failed to send password reset email. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        // Get token from either request body or query parameter
        $token = $request->input('token') ?? $request->query('token');
        
        $validated = $request->validate([
            'newPassword' => ['required', Password::min(8)],
        ]);

        if (!$token) {
            return response()->json(['msg' => 'Token is required'], 400);
        }

        try {
            $secret = (string) env('JWT_SECRET');
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            $email = $decoded->email ?? null;
        } catch (Throwable $e) {
            return response()->json(['msg' => 'Invalid or expired token'], 400);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['msg' => 'Invalid or expired token'], 400);
        }

        $user->password = Hash::make($validated['newPassword']);
        $user->save();

        return response()->json(['msg' => 'Password reset successfully. You can now log in with your new password.']);
    }

    public function logout(Request $request)
    {
        $authorizationHeader = (string) $request->header('Authorization', '');
        if ($authorizationHeader === '' || !str_starts_with($authorizationHeader, 'Bearer ')) {
            return response()->json(['msg' => 'Missing bearer token'], 400);
        }

        $rawToken = substr($authorizationHeader, 7);
        $blacklistKey = 'jwt_blacklist_' . hash('sha256', $rawToken);

        try {
            $secret = (string) env('JWT_SECRET');
            $decodedPayload = JWT::decode($rawToken, new Key($secret, 'HS256'));
            $expiresAtUnix = isset($decodedPayload->exp) ? (int) $decodedPayload->exp : (time() + 3600);
        } catch (Throwable $e) {
            // If token is invalid/expired, still create a short-lived blacklist entry
            $expiresAtUnix = time() + 300;
        }

        $secondsToLive = max(0, $expiresAtUnix - time());
        if ($secondsToLive > 0) {
            Cache::put($blacklistKey, true, $secondsToLive);
        }

        return response()->json(['msg' => 'Logged out successfully']);
    }

    
}