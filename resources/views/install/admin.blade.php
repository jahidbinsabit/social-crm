@extends('install.layout')

@section('content')
    <h2>Administrator account</h2>
    <p class="stepdesc">This is the first account you will sign in with. It gets the <strong>admin</strong> role and can create staff accounts afterwards.</p>

    <form method="POST" action="{{ route('install.admin.next') }}">
        @csrf

        <div class="field">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $admin['name'] ?? '') }}" required autocomplete="name">
        </div>

        <div class="field">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" value="{{ old('email', $admin['email'] ?? '') }}" required autocomplete="email">
        </div>

        <div class="row">
            <div class="field">
                <label for="password">Password</label>
                {{-- Passwords are never re-rendered. --}}
                <input type="password" id="password" name="password" value="" required minlength="10" autocomplete="new-password">
                <p class="hint">Minimum 10 characters.</p>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" value="" required minlength="10" autocomplete="new-password">
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-secondary" href="{{ route('install.keys') }}">&larr; Back</a>
            <button type="submit" class="btn">Next: Review &rarr;</button>
        </div>
    </form>
@endsection
