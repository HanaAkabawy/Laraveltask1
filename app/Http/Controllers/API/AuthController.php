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
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    public function __construct()
    {
        // Apply middleware to protect admin routes
        $this->middleware('auth.jwt')->except(['apiLogin', 'apiRegister', 'forgotPassword', 'resetPassword']);
        $this->middleware('permission:manage users')->only(['getAllUsers', 'getUserById', 'updateUser', 'deleteUser']);
        $this->middleware('role:admin')->only(['assignRole', 'removeRole', 'createRole', 'createPermission']);
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

        // Assign default role to new users
        $user->assignRole('user');

        // Email verification JWT (1 hour)
        $secret = (string) env('JWT_SECRET');
        $token = JWT::encode([
            'email' => $user->email,
            'exp' => time() + 3600,
        ], $secret, 'HS256');

        return response()->json([
            'msg' => 'User registered and assigned default role.',
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

        // Include user roles and permissions in login response
        $userWithRoles = $user->load('roles', 'permissions');

        return response()->json([
            'msg' => 'Logged in successfully', 
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $userWithRoles->getRoleNames(),
                'permissions' => $userWithRoles->getAllPermissions()->pluck('name')
            ]
        ]);
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

        $frontUrl =  env('FRONTEND_URL');
        $resetEndpoint = $frontUrl . '/resetpass';
        $resetUrl = $resetEndpoint . '?token=' . urlencode($token);

        try {
            Mail::send('emails.forgot-password', [
                'user' => $user,
                'token' => $token,
                'resetUrl' => $resetUrl
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
            $expiresAtUnix = time() + 300;
        }

        $secondsToLive = max(0, $expiresAtUnix - time());
        if ($secondsToLive > 0) {
            Cache::put($blacklistKey, true, $secondsToLive);
        }

        return response()->json(['msg' => 'Logged out successfully']);
    }

    // Protected user management methods (require permissions)
    public function getAllUsers()
    {
        $users = User::with('roles', 'permissions')->select('id', 'name', 'email')->get();
        
        $usersWithRoles = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name')
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $usersWithRoles
        ]);
    }

    public function getUserById($id)
    {
        $user = User::with('roles', 'permissions')->find($id);
        if (!$user) {
            return response()->json([
                'msg' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name')
            ]
        ]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'msg' => 'User not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8'
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (isset($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'msg' => 'User updated successfully',
            'data' => $user->load('roles', 'permissions')
        ]);
    }

    public function deleteUser($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json([
                'msg' => 'User not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'msg' => 'User deleted successfully'
        ]);
    }

    // New role and permission management methods
    public function assignRole(Request $request, $userId)
    {
        $validated = $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['msg' => 'User not found'], 404);
        }

        $user->assignRole($validated['role']);

        return response()->json([
            'msg' => "Role '{$validated['role']}' assigned to user successfully",
            'user_roles' => $user->getRoleNames()
        ]);
    }

    public function removeRole(Request $request, $userId)
    {
        $validated = $request->validate([
            'role' => 'required|string|exists:roles,name',
        ]);

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['msg' => 'User not found'], 404);
        }

        $user->removeRole($validated['role']);

        return response()->json([
            'msg' => "Role '{$validated['role']}' removed from user successfully",
            'user_roles' => $user->getRoleNames()
        ]);
    }

    public function createRole(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $role = Role::create(['name' => $validated['name']]);

        if (isset($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        return response()->json([
            'msg' => 'Role created successfully',
            'role' => $role->load('permissions')
        ]);
    }

    public function createPermission(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        $permission = Permission::create(['name' => $validated['name']]);

        return response()->json([
            'msg' => 'Permission created successfully',
            'permission' => $permission
        ]);
    }

    public function getUserProfile()
    {
        $user = auth()->user()->load('roles', 'permissions');
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name')
            ]
        ]);
    }

    public function checkPermission(Request $request)
    {
        $validated = $request->validate([
            'permission' => 'required|string'
        ]);

        $hasPermission = auth()->user()->can($validated['permission']);

        return response()->json([
            'permission' => $validated['permission'],
            'has_permission' => $hasPermission
        ]);
    }
}