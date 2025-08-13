<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reset Your Password</title>
</head>
<body>
  <div style="font-family: Arial, sans-serif; max-width: 600px; padding: 20px; border: 1px solid #ddd; border-radius: 10px;">
    <h2 style="color: #0073e6;">Reset Your Password 🔑</h2>
    <p>Dear {{ $user->name }},</p>
    <p>We received a request to reset your password. Use the link below to reset it:</p>
    <p><strong>Reset Link:</strong> <a href="{{ $resetUrl }}?token={{ urlencode($token) }}">{{ $resetUrl }}?token={{ urlencode($token) }}</a></p>
    <p>This link will expire in 15 minutes. If you did not request this, please ignore this email.</p>
    <hr>
    <p style="font-size: 14px; color: gray;">
      Best Regards, <br>
      <strong>Hana Akabawy</strong> <br>
      Jobsy <br>
      Need help? Contact us at <a href="mailto:support@jobsy.com">support@etax.com</a>
    </p>
  </div>
</body>
</html>
