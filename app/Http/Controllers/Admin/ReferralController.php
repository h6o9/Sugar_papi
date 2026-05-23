<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\ReferralLink;
use App\Models\ReferralLinkSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ReferralController extends Controller
{
    
    /**
     * 1. Generate referral link for authenticated user
     * POST /api/referral/generate
     */
    public function generateLink(Request $request)
    {
        $user = $request->user();

        // Check if user already has a referral link
        $existingLink = ReferralLink::where('user_id', $user->id)->first();
        
        if ($existingLink) {
            return response()->json([
                'success' => true,
                'data' => [
                    'referral_code' => $existingLink->referral_code,
                    'referral_url' => $existingLink->referral_url,
                    'message' => 'Your existing referral link'
                ]
            ]);
        }

        // Generate unique referral code
        do {
            $referralCode = Str::random(8) . $user->id;
        } while (ReferralLink::where('referral_code', $referralCode)->exists());

        // Create deep link URL for mobile app
        $referralUrl = 'yourapp://register?code=' . $referralCode;

        // Save to database
        $referralLink = ReferralLink::create([
            'user_id' => $user->id,
            'referral_code' => $referralCode,
            'referral_url' => $referralUrl
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $referralLink->referral_code,
                'referral_url' => $referralLink->referral_url
            ]
        ]);
    }

    /**
     * 2. Get user's referral link and points
     * GET /api/referral/my-link
     */
    public function getMyLink(Request $request)
    {
        $user = $request->user();
        
        $referralLink = ReferralLink::where('user_id', $user->id)->first();

        if (!$referralLink) {
            return response()->json([
                'success' => false,
                'message' => 'No referral link found. Generate one first.'
            ], 404);
        }

        // Get points per referral from settings
        $settings = ReferralLinkSetting::where('is_active', 1)->first();
        $pointsPerReferral = $settings ? $settings->reward_points : 1;

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $referralLink->referral_code,
                'referral_url' => $referralLink->referral_url,
                'your_points' => $user->referral_points,
                'points_per_referral' => $pointsPerReferral,
                'total_clicks' => $referralLink->total_clicks,
                'successful_registrations' => $referralLink->successful_registrations
            ]
        ]);
    }

    /**
     * 3. Get complete referral statistics
     * GET /api/referral/stats
     */
    public function getStats(Request $request)
    {
        $user = $request->user();
        
        $referralLink = ReferralLink::where('user_id', $user->id)->first();
        
        // Users who registered using this user's referral
        $referredUsers = User::where('referred_by', $user->id)->get();
        
        // Get settings
        $settings = ReferralLinkSetting::where('is_active', 1)->first();
        $pointsPerReferral = $settings ? $settings->reward_points : 1;

        return response()->json([
            'success' => true,
            'data' => [
                'total_points' => $user->referral_points,
                'points_per_referral' => $pointsPerReferral,
                'total_referrals' => $referredUsers->count(),
                'total_clicks' => $referralLink ? $referralLink->total_clicks : 0,
                'successful_registrations' => $referralLink ? $referralLink->successful_registrations : 0,
                'referred_users' => $referredUsers->map(function($user) {
                    return [
                        'name' => $user->name,
                        'email' => $user->email,
                        'joined_at' => $user->created_at ? $user->created_at->toDateString() : null
                    ];
                })
            ]
        ]);
    }

    /**
     * 4. Check if referral code is valid (before registration)
     * GET /api/referral/validate/{code}
     */
    public function validateReferralCode($code)
    {
        $referralLink = ReferralLink::where('referral_code', $code)->first();
        
        // Increment click count if code exists
        if ($referralLink) {
            $referralLink->increment('total_clicks');
            
            $settings = ReferralLinkSetting::where('is_active', 1)->first();
            $pointsPerReferral = $settings ? $settings->reward_points : 1;
            
            return response()->json([
                'success' => true,
                'valid' => true,
                'data' => [
                    'referrer_name' => $referralLink->user->name ?? 'User',
                    'points_you_will_earn' => 0,
                    'points_referrer_will_earn' => $pointsPerReferral
                ]
            ]);
        }
        
        return response()->json([
            'success' => true,
            'valid' => false,
            'message' => 'Invalid referral code'
        ]);
    }

    /**
     * 5. Register new user with referral code
     * POST /api/register-with-referral
     */
    public function registerWithReferral(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'referral_code' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Find the referral link
        $referralLink = ReferralLink::where('referral_code', $request->referral_code)->first();
        
        if (!$referralLink) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid referral code'
            ], 400);
        }

        // Get reward points from settings
        $settings = ReferralLinkSetting::where('is_active', 1)->first();
        $rewardPoints = $settings ? $settings->reward_points : 1;

        // Create new user
        $newUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'referred_by' => $referralLink->user_id,
            'referral_code_used' => $request->referral_code
        ]);

        // Add points to the referrer (who shared the link)
        $referrer = User::find($referralLink->user_id);
        if ($referrer) {
            $referrer->referral_points += $rewardPoints;
            $referrer->save();
        }

        // Update referral link stats
        $referralLink->increment('successful_registrations');

        return response()->json([
            'success' => true,
            'message' => 'Registration successful!',
            'data' => [
                'user' => [
                    'id' => $newUser->id,
                    'name' => $newUser->name,
                    'email' => $newUser->email,
                    'referral_points' => $newUser->referral_points
                ]
            ]
        ], 201);
    }

    /**
     * 6. Use points (redeem for rewards)
     * POST /api/referral/use-points
     */
    public function usePoints(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'points' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        if ($user->referral_points >= $request->points) {
            $user->referral_points -= $request->points;
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => $request->points . ' points used successfully',
                'remaining_points' => $user->referral_points
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Insufficient points',
            'available_points' => $user->referral_points
        ], 400);
    }
}

