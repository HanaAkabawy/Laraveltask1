
<!DOCTYPE html>
<html>
<body>

<form method="POST" action="{{ route('forgotpass') }}">
    @csrf
    

    E-mail: <input type="email" name="email" value="{{ old('email') }}" required><br>
    @error('email') <div>{{ $message }}</div> @enderror


    
    <input type="submit" value="Reset">
</form>

