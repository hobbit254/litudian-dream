<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Mail\EmailVerificationMail;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where(['email' => $request->get('email')])->first();

        if (!$user || $user->email_verified_at == null) {
            return ResponseHelper::error([], 'The user has not been verified.', 401);
        }

        if (!$token = Auth::guard('api')->attempt($credentials)) {
            return ResponseHelper::error([], 'Invalid credentials provided', 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * This is the method used to create a new user
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'string', 'min:36', 'max:36'],
        ]);

        $verificationToken = Str::random(60);
        try {
            $role = Role::where('uuid', $request->get('role_id'))->first();

            if (!$role) {
                // Handle the case where the provided UUID doesn't exist
                return ResponseHelper::error([], 'Invalid role identifier.', 404);
            }
            DB::beginTransaction();
            $user = User::create([
                'name' => $request->get('name'),
                'email' => $request->get('email'),
                'password' => Hash::make($request->get('password')),
                'verification_token' => $verificationToken,
            ]);

            UserRole::create([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);
            DB::commit();

            Mail::to($user->email)->send(new EmailVerificationMail($user));

            return ResponseHelper::success($user->toArray(),
                'Registration successful. Please check your email to verify your account.', 201);
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            DB::rollBack();
            return ResponseHelper::error([], 'An exception was caught when trying to save the data to db', 500);
        }
    }

    /**
     * This method is used to verify the users email against the token sent to their mail
     * @param Request $request
     * @param $id
     * @param $hash
     * @return JsonResponse
     */
    public function verifyEmail(Request $request, $id, $hash): JsonResponse
    {
        // The 'signed' middleware already checks validity, signature, and expiration.
        // We just check the user ID and hash.

        $user = User::find($id);
        // 1. Check if user exists and hash matches the current email
        if (!$user || $hash !== sha1($user->email)) {
            return response()->json([ 'redirect' => 'https://reneeimports.com/auth', 'message' => 'Invalid verification link.' ]);
        }

        // 2. Check if already verified
        if ($user->hasVerifiedEmail()) { // Uses the MustVerifyEmail trait method
            return response()->json([ 'redirect' => 'https://reneeimports.com/auth', 'message' => 'Email already verified.' ]);
        }

        // 3. Mark as verified
        $user->markEmailAsVerified(); // Uses the MustVerifyEmail trait method

        $user->active = 1;
        $user->save();
        return response()->json(['redirect' => 'https://reneeimports.com/auth', 'message' => 'Your email has been verified.']);
    }

    public function logout(): JsonResponse
    {
        // Invalidate the token provided by the user
        Auth::guard('api')->logout();

        return ResponseHelper::success([], 'Successfully logged out.', 200);
    }

    protected function respondWithToken(string $token, int $status = 200): JsonResponse
    {
        $user = Auth::guard('api')->user()->load(['roles']);
        $userData = $user->toArray();
        if ($user->roles->isNotEmpty()) {
            $userData['role_name'] = $user->roles->first()->role_name;
        } else {
            $userData['role_name'] = null;
        }
        unset($userData['roles']);

        return ResponseHelper::success([
            'user' => $userData,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ], 'Successful login', $status);

    }
}
