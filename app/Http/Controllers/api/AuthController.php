<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\RespondTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{

    use RespondTrait;

    public function signup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|confirmed|string|min:8',
            'phone' => 'nullable|string|unique:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation Error',
                'data' => collect($validator->errors())->map(fn($messages) => $messages[0])->toArray(),
            ], 400);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'status' => 0,
            'role' => 2
        ]);

        $otp = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);

        Otp::where('email', $request->email)->delete();
        Otp::create([
            'email' => $request->email,
            'otp' => Hash::make($otp),
            'expires_at' => Carbon::now()->addMinutes(10)
        ]);

        try {
            Mail::send('emails.registration-otp', ['otp' => $otp, 'username' => $user->name], function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('Your OTP for Account Verification');
            });

            return response()->json([
                'status' => true,
                'message' => 'User registered successfully. OTP sent to email.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to send OTP',
            ], 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'error' => 'Validation Error',
                'data' => collect($validator->errors())->map(fn($messages) => $messages[0])->toArray(),
            ], 400);
        }

        $otpRecord = Otp::where('email', $request->email)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();

        if (!$otpRecord || !Hash::check($request->otp, $otpRecord->otp)) {
            return response()->json(['status' => false, 'error' => 'Invalid or expired OTP'], 400);
        }

        $user = User::where('email', $request->email)->first();
        $user->email_verified_at = Carbon::now();
        $user->save();

        // Delete OTP record
        $otpRecord->delete();

        return response()->json(['status' => true, 'message' => 'Email verified successfully']);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid login credentials'
            ], 401);
        }

        $user = Auth::user();

        if ($user->status !== 1) {
            return response()->json([
                'status' => false,
                'message' => 'Account is inactive or not verified'
            ], 401);
        }

        // Revoke previous tokens
        $user->tokens()->delete();

        // Generate new sanctum token
        $token = $user->createToken($request->email);

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token->plainTextToken,
                'token_type' => 'bearer',
                'expires_in' => config('auth.sanctum', 60) * 60
            ]
        ]);
    }

    public function me()
    {
        $user = Auth::user();

        return response()->json([
            'status' => true,
            'data' => [
                'user' => $user
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Successfully logged out'
        ]);
    }

    public function refresh(Request $request)
    {
        $user = Auth::user();

        // Revoke the current access token
        $request->user()->tokens()->delete();

        // Generate a new token
        $newToken = $user->createToken($user->email)->plainTextToken;

        return response()->json([
            'status' => true,
            'data' => [
                'token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => config('auth.passwords.users.expire') * 60
            ]
        ]);
    }
    


}
