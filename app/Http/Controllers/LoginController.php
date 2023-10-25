<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class LoginController extends Controller
{
    public function index()
    {
        return view('auth.login');
    }

    public function registration() {
        return view('auth.registration');
    }

    public function register(Request $request) {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6'
        ]);

        $data = $request->all();

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'])
        ]);

        return redirect('login')->with('success', 'Registration');
    }

    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        $credentials = $request->only('email', 'password');

        if(Auth::attempt($credentials)){
            $token = md5(uniqid());

            User::where('id', Auth::id())->update(['token' => $token]);
            return redirect('dashboard');
        }

        return redirect('login')->with('success', 'Login');
    }

    public function dashboard() {
        if (Auth::check()) {
            return view('dashboard');
        }

        return redirect('login')->with('success', 'Dashboard');

    }

    public function profile() {
        if (Auth::check()) {
            $data = User::where('id', Auth::id())->get();
            return view('auth.profile', compact('data'));
        }

        return redirect('login')->with('success', 'Profile');

    }

    public function profile_action(Request $request) {
        $request->validate([
            'name' => 'required',
            'email' => 'required',
            'user_image' => 'image|mimes:png,jpg,jpeg|max:2048|dimensions:min_with=100,min_height=100,max_width=1000,max_height=1000'
        ]);

        $user_image = $request->hidden_user_image;

        if ($request->user_image != '') {
            $user_image = time().'.'.$request->user_image->getClientOriginalExtension();
            $request->user_image->move(public_path('images'),$user_image);
        }

        $user = User::find(Auth::id());
        $user->name = $request->name;
        $user->email = $request->email;

        if ($request->password != '') {
            $user->password = Hash::make($request->password);
        }

        $user->user_image = $user_image;
        $user->save();
        return redirect('login')->with('success', 'Profile 1');
    }

    public function logout() {
        Session::flush();
        Auth::logout();
        return redirect('login')->with('success', 'Logout');
    }
}
