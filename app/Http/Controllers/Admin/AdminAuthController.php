<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;

class AdminAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login', ['errors' => session()->get('errors', new MessageBag())]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('errors', $validator)->withInput();
        }

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return redirect('/admin/telescope');
        }

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
        ])->withInput($request->only('email'));
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('login');
    }
}
