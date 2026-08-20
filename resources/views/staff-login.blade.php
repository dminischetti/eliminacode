<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Accesso banco — {{ config('app.name') }}</title>
<style>
    :root { --tile:#EEF1F0; --ink:#16181A; --steel:#5C6470; --line:#D6DBDA; --insegna:#9E1B1B; --paper:#FFF; }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; background: var(--tile); color: var(--ink);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        display: flex; align-items: center; justify-content: center; padding: 1.5rem;
    }
    form { width: 100%; max-width: 380px; }
    h1 { font-size: 1.35rem; margin: 0 0 1.5rem; letter-spacing: -0.01em; }
    label { display: block; font-size: 0.95rem; color: var(--steel); margin-bottom: 0.4rem; }
    input {
        width: 100%; min-height: 60px; margin-bottom: 1.1rem; padding: 0 1rem;
        border: 1px solid var(--line); border-radius: 12px; background: var(--paper);
        font-family: inherit; font-size: 1.15rem; color: var(--ink);
    }
    input:focus-visible { outline: 3px solid var(--ink); outline-offset: 2px; }
    button {
        width: 100%; min-height: 64px; border: 0; border-radius: 12px;
        background: var(--ink); color: var(--paper);
        font-family: inherit; font-size: 1.2rem; font-weight: 700; cursor: pointer;
    }
    .errore {
        margin: 0 0 1.1rem; padding: 0.8rem 1rem; border-radius: 10px;
        background: var(--insegna); color: var(--paper); font-size: 1rem;
    }
</style>
</head>
<body>

<form method="POST" action="/staff/login">
    @csrf
    <h1>Accesso banco</h1>

    @if ($errors->any())
        <p class="errore">{{ $errors->first() }}</p>
    @endif

    <label for="email">Email</label>
    <input id="email" name="email" type="email" inputmode="email" autocomplete="username"
           value="{{ old('email') }}" required autofocus>

    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>

    <button type="submit">ENTRA</button>
</form>

</body>
</html>
