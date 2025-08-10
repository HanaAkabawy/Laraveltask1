
<!DOCTYPE html>
<html>
<body>

<form method="POST" action="{{ route('register') }}">
    @csrf
    Name: <input type="text" name="name" value="{{ old('name') }}" required><br>
    @error('name') <div>{{ $message }}</div> @enderror

    E-mail: <input type="email" name="email" value="{{ old('email') }}" required><br>
    @error('email') <div>{{ $message }}</div> @enderror

    Password: <input type="password" name="password" required autocomplete="new-password"><br>
    @error('password') <div>{{ $message }}</div> @enderror

    Confirm Password: <input type="password" name="password_confirmation" required><br>

    <input type="submit" value="Register">
</form>

<a href="{{ route('login') }}">Already have an account?