<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use App\RespondTrait;
use Carbon\Carbon;
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

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

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

    


}
