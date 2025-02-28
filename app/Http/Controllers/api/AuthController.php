<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
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

    public $otp;
    public $otp_expiry;

    public function __construct()
    {
        $this->otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->otp_expiry = Carbon::now()->addMinutes(2);
    }

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
                'data' => array_values($validator->errors()->all()),
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

        try {
            $request->user()->sendEmailVerificationNotification();

            Mail::send('emails.registration-otp', ['otp' => $this->otp], function ($message) use ($request) {
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

}
