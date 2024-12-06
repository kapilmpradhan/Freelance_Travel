<!-- resources/views/auth/login.blade.php -->

@extends('layouts.app')

@section('content')
<div class="max-w-md mx-auto bg-white p-6 rounded-lg shadow-md">
    <h2 class="text-2xl font-bold text-center mb-6">Login</h2>

    <!-- Login Form -->
    <form method="POST" action="{{ route('login.submit') }}">
        @csrf

        <!-- Email -->
        <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-2 block w-full p-3 border rounded-md" placeholder="Your email address">
        </div>

        <!-- Password -->
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input id="password" type="password" name="password" required class="mt-2 block w-full p-3 border rounded-md" placeholder="Your password">
        </div>

        <!-- Remember Me -->
        <div class="mb-4 flex items-center">
            <input id="remember" type="checkbox" name="remember" class="mr-2">
            <label for="remember" class="text-sm text-gray-600">Remember Me</label>
        </div>

        <!-- Submit Button -->
        <div>
            <button type="submit" class="w-full py-3 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                Login
            </button>
        </div>
    </form>
</div>
@endsection
