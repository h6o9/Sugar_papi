<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Mail\UserConfirmRegistration;
use App\Mail\UserPasswordReset;
use App\Mail\UserRegisterMail;
use App\Mail\UserResetPassword;
use App\Models\Reward;
use App\Models\User;
use App\Http\Requests\AntiBotFormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function getlogin()
    {

        return view('home.auth.login');
    }
    public function userLogin(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);
        $credentials = $request->only('email', 'password');
        if (auth::guard('user')->attempt($credentials)) {
            if (count(session('cart', [])) > 0) {
                return redirect()->route('checkout')->with(['status' => true, 'message' => 'Login Successfully']);
            } else {

                return redirect()->route('index')->with(['status' => true, 'message' => 'Login Successfully']);
            }
        }
        return back()->with(['status' => true, 'message' => 'Invalid Email or Password']);
    }
    public function getRegistor()
    {

        return view('home.auth.register');
    }
    public function storeUser(AntiBotFormRequest $request)
    {

        $validatedData = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'g-recaptcha-response' => 'required|recaptcha',
            'phone' => 'required|numeric|max:15|unique:users,phone',
            'address' => 'required|string|max:255',
            'postcode' => 'required|string|max:10',
        ]);
        $user = new User();
        $user->name = $validatedData['name'];
        $user->email = $validatedData['email'];
        $user->phone = $validatedData['phone'];
        $user->address = $validatedData['address'];
        $user->postcode = $validatedData['postcode'];
        $user->password = Hash::make($validatedData['password']);
        $user->save();
        if ($user) {
            $data['username'] = $user->name;
            $data['useremail'] = $user->email;
            $data['password'] = $request->password;
            Mail::to($user->email)->send(new UserConfirmRegistration($data));
            return redirect('/login')->with(['status' => true, 'message' => 'Register Succssfully']);
        } else {
            return redirect()->back()->with(['status' => true, 'message' => 'Something Went Wrong,Try Again!']);
        }
    }
    public function getcontact()
    {

        return view('home.contact');
    }
    public function getProfile()
    {
        $userId = auth::guard('user')->id();
        $user = User::where('id', $userId)->first();
        $reward = Reward::where('user_id', $user->id)->first();
        return view('home.my-profile', compact('user', 'reward'));
    }

    public function updateProfile(Request $request, $id)
    {
        // ✅ Validation
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:20',
            'password' => 'nullable|confirmed|min:6',
        ]);

        // ✅ Find User
        $user = User::findOrFail($id);

        // ✅ Base Data
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'postcode' => $request->postcode,
        ];

        // ✅ Password update only if entered
        if (!empty($request->password)) {
            $data['password'] = bcrypt($request->password);
        }

        // ✅ Update
        $user->update($data);

        return redirect()->back()->with('message', 'Profile updated successfully!');
    }
    public function forgotPassword()
    {
        return view('home.auth.forgot-password');
    }
    public function userResetPasswordLink(AntiBotFormRequest $request)
    {

        $request->validate([
            'email' => 'required|exists:users,email',
            'g-recaptcha-response' => 'required|recaptcha',
        ]);
        $exists = DB::table('password_resets')->where('email', $request->email)->first();
        if ($exists) {
            return back()->with(['status' => true, 'message' => 'Reset Password Link Already Send Successfully']);
        } else {
            $token = Str::random(30);
            DB::table('password_resets')->insert([
                'email' => $request->email,
                'token' => $token,
            ]);
            $user['url'] = url('userChange_password', $token);
            Mail::to($request->email)->send(new UserResetPassword($user));
            return back()->with(['status' => true, 'message' => 'Reset Password Link Send Successfully']);
        }
    }
    public function userChange_password($id)
    {
        $user = DB::table('password_resets')->where('token', $id)->first();
        if (isset($user)) {
            return view('home.auth.reset-password', compact('user'));
        }
    }
    public function resetPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:8',
            'confirmed' => 'required',

        ]);
        if ($request->password != $request->confirmed) {
            return back()->with(['status' => true, 'message' => 'Password not matched']);
        }
        $password = bcrypt($request->password);
        $tags_data = [
            'password' => bcrypt($request->password)
        ];
        if (User::where('email', $request->email)->update($tags_data)) {
            DB::table('password_resets')->where('email', $request->email)->delete();
            return redirect('/login')->with(['status' => true, 'message' => 'Reset Password Successfully']);
        }
    }

    public function userLogout()
    {
        Auth::guard('user')->logout();
        return redirect('login')->with(['status' => true, 'message' => 'Logout Successfully']);
    }
}
