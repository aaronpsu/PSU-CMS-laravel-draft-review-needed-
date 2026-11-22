<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function setupStatus(Request $request): JsonResponse
    {
        $hasAdmin = DB::table('users')
            ->where('role', 'admin')
            ->where('status', 'active')
            ->exists();

        return response()->json(['setupRequired' => ! $hasAdmin]);
    }

    public function setupInitial(Request $request): JsonResponse
    {
        $hasAdmin = DB::table('users')
            ->where('role', 'admin')
            ->where('status', 'active')
            ->exists();
        abort_if($hasAdmin, 409, 'An active administrator already exists.');

        $request->merge([
            'fullName' => trim((string) $request->input('fullName')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:150', 'regex:/\S/'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:12', 'max:1024'],
        ]);

        $roleId = DB::table('roles')
            ->whereIn('name', ['Super Administrator', 'Administrator'])
            ->orderByRaw("CASE WHEN name = 'Administrator' THEN 0 ELSE 1 END")
            ->value('role_id');
        abort_unless($roleId, 422, 'The administrator role is unavailable.');

        $campusId = DB::table('campuses')->orderBy('campus_id')->value('campus_id');
        if (! $campusId) {
            $campusId = DB::table('campuses')->insertGetId([
                'code' => 'MAIN',
                'name' => 'Main Campus',
                'is_main' => 1,
                'address' => null,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ], 'campus_id');
        }

        $nameParts = preg_split('/\s+/', $data['fullName'], 2) ?: [$data['fullName']];
        $baseUsername = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $data['fullName']) ?: 'admin');
        $username = $baseUsername;
        $suffix = 2;
        while (DB::table('users')->where('username', $username)->exists()) {
            $username = $baseUsername . $suffix++;
        }

        $userId = DB::table('users')->insertGetId([
            'role_id' => $roleId,
            'role' => 'admin',
            'campus_id' => $campusId,
            'username' => $username,
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1] ?? '',
            'full_name' => $data['fullName'],
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'user_id');

        $user = User::findOrFail($userId);
        $request->session()->regenerate();
        Auth::guard('web')->login($user);

        return response()->json([
            'user' => $this->mePayload($user),
            'csrfToken' => $request->session()->token(),
        ], 201);
    }

    public function csrf(Request $request): JsonResponse
    {
        $request->session()->start();
        return response()->json(['csrfToken' => $request->session()->token()]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
        $email = strtolower(trim($data['email']));
        $key = 'cms-login:' . sha1($email . '|' . $request->ip());
        $ipKey = 'cms-login-ip:' . sha1($request->ip());
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Please try again later.',
            ]);
        }

        $user = User::where('email', $email)->where('status', 'active')->first();
        if (! $user || ! Hash::check($data['password'], (string) $user->password_hash)) {
            RateLimiter::hit($key, 15 * 60);
            RateLimiter::hit($ipKey, 15 * 60);
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($ipKey);
        $request->session()->regenerate();
        Auth::guard('web')->login($user);
        return response()->json([
            'user' => $this->mePayload($user),
            'csrfToken' => $request->session()->token(),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->mePayload($request->user())]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;

        return response()->json([
            'data' => DB::table('notifications')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->orderByDesc('notification_id')
                ->limit(20)
                ->get([
                    'notification_id',
                    'type',
                    'message',
                    'entity_type',
                    'entity_id',
                    'is_read',
                    'created_at',
                ]),
            'unreadCount' => DB::table('notifications')
                ->where('user_id', $userId)
                ->where('is_read', false)
                ->count(),
        ]);
    }

    public function markNotificationRead(Request $request, int $id): JsonResponse
    {
        $updated = DB::table('notifications')
            ->where('notification_id', $id)
            ->where('user_id', $request->user()->user_id)
            ->update(['is_read' => true]);

        abort_unless($updated > 0, 404, 'Notification not found.');

        return response()->json(['read' => true]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json([
            'loggedOut' => true,
            'csrfToken' => $request->session()->token(),
        ]);
    }

    public function updateAccount(Request $request): JsonResponse
    {
        $request->merge([
            'fullName' => trim((string) $request->input('fullName')),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);
        $data = $request->validate([
            'fullName' => ['required', 'string', 'max:150', 'regex:/\S/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $request->user()->user_id . ',user_id'],
        ]);
        $user = $request->user();
        $fullName = trim($data['fullName']);
        $nameParts = preg_split('/\s+/', $fullName, 2) ?: [$fullName];
        $user->full_name = $fullName;
        $user->first_name = $nameParts[0];
        $user->last_name = $nameParts[1] ?? '';
        $user->email = strtolower(trim($data['email']));
        $user->save();
        return response()->json(['user' => $this->mePayload($user)]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currentPassword' => ['required', 'string', 'max:1024'],
            'newPassword' => ['required', 'string', 'min:12', 'max:1024'],
        ]);
        $user = $request->user();
        if (! Hash::check($data['currentPassword'], (string) $user->password_hash)) {
            throw ValidationException::withMessages(['currentPassword' => 'The current password is incorrect.']);
        }
        if (Hash::check($data['newPassword'], (string) $user->password_hash)) {
            throw ValidationException::withMessages(['newPassword' => 'Choose a password different from the current password.']);
        }
        $user->password_hash = Hash::make($data['newPassword']);
        $user->save();
        $request->session()->regenerate(true);
        return response()->json([
            'changed' => true,
            'csrfToken' => $request->session()->token(),
        ]);
    }

    private function mePayload(?User $user): ?array
    {
        if (! $user) return null;
        return [
            'id' => $user->user_id,
            'name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}
