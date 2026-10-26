<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="0; url={{ asset('challenges/secret/index.html') }}">
    <title>secreT — Challenge Target</title>
    <script>
        window.location.replace("{{ asset('challenges/secret/index.html') }}");
    </script>
</head>
<body style="background-color: #0b0f19; color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; text-align: center; padding: 20px;">
    <div>
        <p style="font-size: 18px; margin-bottom: 12px;">Opening secreT Challenge Portal...</p>
        <p style="font-size: 14px; color: #94a3b8;">
            If you are not redirected automatically, <a href="{{ asset('challenges/secret/index.html') }}" style="color: #38bdf8; text-decoration: underline;">click here to enter the portal</a>.
        </p>
    </div>
</body>
</html>

