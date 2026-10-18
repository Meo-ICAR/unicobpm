<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="referrer" content="no-referrer">
    <title>Passaggio a {{ $label }}</title>
</head>
<body onload="document.forms[0].submit()">
    <form method="POST" action="{{ $action }}">
        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="user_email" value="{{ $email }}">
        <noscript><button type="submit">Vai a {{ $label }}</button></noscript>
    </form>
</body>
</html>
