
<!DOCTYPE html>
<html>
<body>

<form method="POST" action="{{ route('login') }}">
    @csrf
    

    E-mail: <input type="email" name="email" value="{{ old('email') }}" required><br>
    @error('email') <div>{{ $message }}</div> @enderror

    Password: <input type="password" name="password" required autocomplete="current-password"><br>
    @error('password') <div>{{ $message }}</div> @enderror

    
    <input type="submit" value="login">
</form>

