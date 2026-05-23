<?php

namespace App\Http\Controllers\Api;

use Google;
use Exception;
use App\Models\User;
use App\Models\EmailOtp;
use App\Mail\RegisterMail;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
public function register(Request $request)
{
    try {

        // ---------------------------
        // CHECK IF EMAIL ALREADY EXISTS IN USERS TABLE
        // ---------------------------
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Email already exists'
            ], 409); // 409 Conflict
        }

        
        // ---------------------------
        // UPDATE OR CREATE email_otps RECORD
        // ---------------------------
        $user = User::updateOrCreate(
            ['email' => $request->email],
            [
                'name'      => $request->name,
                'password'  => Hash::make($request->password),
				'latitude'  => $request->latitude,
				'longitude' => $request->longitude,
				'address'  => $request->address,
				'postcode' => $request->postcode,
            ]
        );


        return response()->json([
            'status'    => 'success',
            'message'   => 'User registered successfully',
			'user'      => $user,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}

public function getProfile()
{
    $user = auth()->user();

    return response()->json([
        'success' => true,
        'data' => [
            'name'  => $user->name,
            'email' => $user->email,
			'image' => $user->image,
			"address" => $user->address,
			"postcode" => $user->postcode,
			"phone" => $user->phone,
        ]
    ]);
}

// public function verifyOtp(Request $request)
// {
//     try {

//         // -----------------------------------------
//         // 1) Find OTP record by email only
//         // -----------------------------------------
//         $otpData = EmailOtp::where('email', $request->email)->first();

//         if (!$otpData) {
//             return response()->json(['error' => 'Email not found'], 400);
//         }

//         // -----------------------------------------
//         // 2) Check if user already exists
//         // -----------------------------------------
//         if (User::where('email', $request->email)->exists()) {
//             return response()->json([
//                 'status'  => 'error',
//                 'message' => 'Email already exists'
//             ], 409);
//         }

//         // -----------------------------------------
//         // 3) EXPIRY CHECK (5 Minutes)
//         // -----------------------------------------
//         $otpCreated = Carbon::parse($otpData->created_at);
//         $now = Carbon::now();
//         $diff = $now->diffInMinutes($otpCreated);

//         if ($diff > 5) {
//             $otpData->delete();
//             return response()->json(['error' => 'OTP expired.please request a new one'], 400);
//         }

//         // -----------------------------------------
//         // 4) OTP MATCH
//         // -----------------------------------------
//         if ($otpData->otp != $request->otp) {
//             return response()->json(['error' => 'Incorrect otp'], 400);
//         }

//         // -----------------------------------------
//         // 5) Create User
//         // -----------------------------------------

//         $user = User::create([
//             'name'     => $otpData->name,
//             'email'    => $otpData->email,
//             'password' => $otpData->password, // password already hashed
//         ]);

//         // -----------------------------------------
//         // 6) Delete OTP record after success
//         // -----------------------------------------
//         $otpData->delete();

//         return response()->json([
//             'message' => 'OTP verified. User registered successfully',
//             'user'    => $user
//         ], 201);

//     } catch (Exception $e) {
//         return response()->json(['error' => $e->getMessage()], 500);
//     }
// }

// public function socialLogin(Request $request)
// {
//     try {
//         $data = $request->only([
//             'social_id',
//             'login_type',
//             'fcm_token',
//             'email',
//             'name',
//             'image',
//             'password'
//         ]);

//         /*
//         |--------------------------------------------------------------------------
//         | CASE 0: Manual Login (Email + Password)
//         |--------------------------------------------------------------------------
//         */
//         if (!empty($data['email']) && !empty($data['password']) && empty($data['social_id'])) {

//             $user = User::where('email', $data['email'])->first();

//             if (!$user || !\Hash::check($data['password'], $user->password)) {
//                 return response()->json([
//                     'status'  => false,
//                     'message' => 'Invalid credentials'
//                 ], 401);
//             }


//             // Update login info (NO access_token save)
//             $user->fcmtoken     = $data['fcm_token'] ?? $user->fcmtoken;
//             $user->login_date  = now();
//             $user->availability = 1;
//             $user->save();

//             // ✅ Sanctum Token
//             $token = $user->createToken('auth_token')->plainTextToken;

//             return response()->json([
//                 'status' => true,
// 				'access_token' => $token,
//                 'message' => 'Logged in successfully',
//                 'user' => $user,
//             ], 200);
//         }

//         /*
//         |--------------------------------------------------------------------------
//         | CASE 1: Social Login (Google / Apple)
//         |--------------------------------------------------------------------------
//         */
//         if (empty($data['social_id']) || empty($data['login_type']) || empty($data['email'])) {
//             return response()->json([
//                 'status'  => false,
//                 'message' => 'social_id, login_type and email are required'
//             ], 422);
//         }

//         $socialColumn = $data['login_type'] === 'apple'
//             ? 'apple_social_id'
//             : 'google_social_id';

//         $user = User::where('email', $data['email'])->first();

//         if (!$user) {
//             return response()->json([
//                 'status'  => false,
//                 'message' => 'This email does not exist'
//             ], 404);
//         }

        

//         // Save social ID once
//         if (empty($user->$socialColumn)) {
//             $user->$socialColumn = $data['social_id'];
//         }

//         // Update profile info (NO access_token save)
//         $user->fcmtoken     = $data['fcm_token'] ?? $user->fcmtoken;
//         $user->login_type  = $data['login_type'];
//         $user->name        = $data['name'] ?? $user->name;
//         $user->image       = $data['image'] ?? $user->image;
//         $user->login_date  = now();
//         $user->availability = 1;
//         $user->save();

//         // ✅ Sanctum Token
//         $token = $user->createToken('auth_token')->plainTextToken;

//         return response()->json([
//             'status' => true,
// 			'access_token' => $token,
//             'message' => 'Logged in successfully',
//             'user' => $user,
//         ], 200);

//     } catch (\Exception $e) {
//         return response()->json([
//             'status'  => false,
//             'message' => 'Something went wrong'
//         ], 500);
//     }
// }

public function Login(Request $request)
{
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required|string|min:6',
        'fcm_token'=> 'nullable|string'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user || empty($user->password) || !\Hash::check($request->password, $user->password)) {
        return response()->json([
            'status'  => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    $user->fcmtoken     = $request->fcm_token ?? $user->fcmtoken;
    $user->login_date   = now();
    $user->availability = 1;
    $user->save();

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'status'       => true,
        'access_token' => $token,
        'message'      => 'Logged in successfully',
        'user'         => $user
    ], 200);
}

public function socialLogin(Request $request)
{
    try {
        $request->validate([
            'social_id'  => 'required|string',
            'login_type' => 'required|in:google,apple',
            'email'      => 'required|email',
            'name'       => 'nullable|string',
            'image'      => 'nullable|url',
            'fcm_token'  => 'nullable|string'
        ]);

        $socialColumn = $request->login_type === 'apple' 
            ? 'apple_social_id' 
            : 'google_social_id';

        // Try to find user by email OR social_id
        $user = User::where('email', $request->email)
                    ->orWhere($socialColumn, $request->social_id)
                    ->first();

        if (!$user) {
            // Auto-register new user
            $user = User::create([
                'email'           => $request->email,
                'name'            => $request->name ?? explode('@', $request->email)[0],
                $socialColumn     => $request->social_id,
                'login_type'      => $request->login_type,
                'image'           => $request->image ?? null,
                'fcmtoken'        => $request->fcm_token ?? null,
                'login_date'      => now(),
                'availability'    => 1,
                'is_active'       => '1',
                'status'          => '1',
                'password'        => bcrypt(uniqid()),
            ]);
        } 
        else {
            // Update existing user
            if (empty($user->$socialColumn)) {
                $user->$socialColumn = $request->social_id;
            }
            
            $user->fcmtoken     = $request->fcm_token ?? $user->fcmtoken;
            $user->login_type   = $request->login_type;
            $user->name         = $request->name ?? $user->name;
            $user->image        = $request->image ?? $user->image;
            $user->login_date   = now();
            $user->availability = 1;
            $user->is_active    = '1';
            $user->status       = '1';
            $user->save();
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // 👇 ORIGINAL RESPONSE FORMAT (jo aapko chahiye)
        return response()->json([
            'status'       => true,
            'access_token' => $token,
            'message'      => 'Google login successful',
            'user'         => $user
        ], 200);
        
    } catch (\Exception $e) {
        return response()->json([
            'status'  => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
public function updateProfile(Request $request)
{
    try {
        $user = auth()->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'postcode' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:500',
            'password' => 'sometimes|string|min:6|confirmed',
            'profile_picture' => 'sometimes|image|mimes:jpg,jpeg,png|max:2048',
            'email' => 'prohibited',
        ]);

        // Check if phone is being changed and validate uniqueness
        $isPhoneChange = $request->filled('phone') && $request->phone != $user->phone;
        
        if ($isPhoneChange) {
            $existingUser = User::where('phone', $request->phone)
                ->where('id', '!=', $user->id)
                ->first();
            if ($existingUser) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Phone number already in use by another user.'
                ], 409);
            }
        }

        // Update name
        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        // Update phone (direct update - NO OTP)
        if ($request->filled('phone')) {
            $user->phone = $request->phone;
        }

        // Update postcode
        if ($request->filled('postcode')) {
            $user->postcode = $request->postcode;
        }

        // Update address
        if ($request->filled('address')) {
            $user->address = $request->address;
        }

        // Update password
        if ($request->filled('password')) {
            $user->password = bcrypt($request->password);
        }

        // Update profile picture
        if ($request->hasFile('profile_picture')) {
            $fileName = time() . '.' . $request->profile_picture->extension();
            $request->profile_picture->move(public_path('admin/assets/images'), $fileName);
            $user->image = 'public/admin/assets/images/' . $fileName;
        }

        $user->save();

        return response()->json([
            'status'  => true,
            'message' => 'Profile updated successfully.'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'error'  => $e->getMessage()
        ], 500);
    }
}


public function verifyUpdateprofileOtp(Request $request)
{

    $otpData = EmailOtp::where('otp', $request->otp)
        ->where('access_token', $request->access_token)
        ->first();

    if (!$otpData) {
        return response()->json([
            'message' => 'Invalid or expired OTP'
        ], 422);
    }

    // 🔥 USER FIND BY EMAIL (NOT ID)
    $user = User::where('email', $otpData->email)->first();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // FINAL UPDATE
    $user->phone = $otpData->phone;
    $user->name  = $otpData->name;
    $user->image = $otpData->image;
    $user->save();

    $otpData->delete();

    return response()->json([
        'message' => 'Profile updated successfully'
    ]);
}


public function logout(Request $request)
{
    try {
        $user = $request->user(); // Sanctum recommended

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        if ($user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Server Error'
        ], 500);
    }
}


public function resendOtp(Request $request)
{
    try {

        $type = $request->type; // email | phone
        $identifier = trim($request->identifier);

        if (!in_array($type, ['email', 'phone'])) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid type provided'
            ], 400);
        }

        // Fetch latest OTP
        $recentOtp = EmailOtp::where(
            $type === 'email' ? 'email' : 'phone',
            $identifier
        )->latest()->first();

        if (!$recentOtp) {
            return response()->json([
                'status' => false,
                'message' => "No OTP record found for this $type"
            ], 404);
        }

        // Generate OTP
        $otp = rand(1000, 9999);
        $otpToken = Str::uuid();

        $recentOtp->update([
            'otp' => $otp,
            'access_token' => $otpToken,
        ]);

        // Send OTP
        if ($type === 'email') {

            Mail::to($identifier)->send(
                new RegisterMail([
                    'name'  => $user->name ?? 'User',
                    'email' => $identifier,
                    'otp'   => $otp,
                ])
            );

        } else {
            // SMS logic (future)
            /*
            $twilio = new Client(
                env('TWILIO_SID'),
                env('TWILIO_TOKEN')
            );

            $twilio->messages->create($identifier, [
                'from' => env('TWILIO_PHONE_NUMBER'),
                'body' => "Your Sugar-Papi OTP is $otp. Do not share it.",
            ]);
            */
        }

        return response()->json([
            'status' => true,
            'message' => "OTP has been resent to your $type.",
            'otp_token' => $otpToken,
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage(),
        ], 500);
    }
}

public function forgetPassword(Request $request)
{
    try {
        // Validate email
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email does not exist'
            ], 404); // Not Found
        }

        // Generate OTP + token
        $otp = rand(1000, 9999); // 4-digit OTP
        $access_token = Str::uuid();

        // Update or create OTP record
        $otpData = EmailOtp::updateOrCreate(
            ['email' => $request->email],
            [
                'otp'       => $otp,
                'access_token' => $access_token,
                'verified'  => false,
                'created_at'=> now(), // reset expiry
            ]
        );

        // Send OTP Email
        Mail::to($request->email)->send(new RegisterMail([
            'name'  => $user->name,
            'email' => $user->email,
            'otp'   => $otp,
        ]));

        return response()->json([
            'status'    => 'success',
            'message'   => 'OTP successfully sent to your email.',
            'access_token' => $access_token
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Something went wrong!',
            'error'   => $e->getMessage()
        ], 500);
    }
}


public function verifyForgetOtp(Request $request)
{
    try {
        $request->validate([
            'email'     => 'required|email',
            'otp'       => 'required|digits:4',
            'access_token' => 'required|string',
        ]);

        $otpData = EmailOtp::where('email', $request->email)
            ->where('access_token', $request->access_token)
            ->first();

        if (!$otpData) {
            return response()->json([
                'status' => 'error',
                'message'=> 'Invalid OTP or token'
            ], 400);
        }

        // Check OTP expiry (5 minutes)
        // $otpCreated = Carbon::parse($otpData->created_at);
        // $now = Carbon::now();
        // $diff = $now->diffInMinutes($otpCreated);

        // if ($diff > 5) {
        //     $otpData->delete();
        //     return response()->json([
        //         'status'  => 'error',
        //         'message' => 'OTP expired. Please request a new one.'
        //     ], 410); // Gone
        // }

        // Check OTP correctness
        if ($otpData->otp != $request->otp) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Incorrect OTP'
            ], 400);
        }

		$otpData->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'OTP successfully verified.'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Internal Server Error',
            'error'   => $e->getMessage()
        ], 500);
    }
}


public function resetPassword(Request $request)
{
    try {
        // Update password in users table
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User does not exist'
            ], 404);
        }

        // ❌ New password same as old password
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This is your old password. please create a new one.'
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Password updated successfully'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Internal Server Error',
            'error'   => $e->getMessage()
        ], 500);
    }
}


Public function ChangePassword(Request $request)
{
    try {
        // Update password in users table
        $data = auth()->user();
		$user = User::where('email', $data->email)->first();
        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User does not exist'
            ], 404);
        }

        // ❌ New password same as old password
        if (Hash::check($request->new_password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This is your old password. please create a new one.'
            ], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Password updated successfully'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Internal Server Error',
            'error'   => $e->getMessage()
        ], 500);
    }
}

public function getLoggedInUser()
{
    $user = auth()->user();

	if(!$user){
		return response()->json([
			'status'  => false,
			'message' => 'User not authenticated',
		], 404);
	}

    return response()->json([
        'status'  => true,
        'message' => 'User fetched successfully',
        'data'    => [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'image' => $user->image 
                ? asset('storage/users/' . $user->image)
                : null
        ]
    ], 200);
}


    public function notification()
    {
        $SERVER_API_KEY = 'AAAAGAYvVyg:APA91bHn703e-8w6gHludk4Wd8Uj1HjFXYp6933n-ZQx-a8qM_Hu86nJh-XlVv7CBUXikcOICEN1TW4sswuAjjeD7RWaCwttgE3R26ZvLGdwkIgHR9HigoxyZusqQucp-i5vdjyqWww8';
        $data = [
            'to' => 'fyRn1eGwRiKUYISE1ePZoU:APA91bEmN9xAvoZpjfcumQ7hvlcG-gFVWaE9vUh8XpobiA5dFKxGHhCxVP8jwHm-VD_gpb1EATIGth3f-WsXvhMmQry6hkCYwRROMZmUO21ghOxcoGm8xulSKkLKLZw3YA-bH_qPnzic',
            'notification' => [
                'title' => "Request",
                'body' => 'asdnsajkldnsalkdmnsakdmsamsadmsadms',
            ],
        ];

        $dataString = json_encode($data);
        $headers = [
            'Authorization: key=' . $SERVER_API_KEY,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        $response = curl_exec($ch);
        dd($response);
    }
}
