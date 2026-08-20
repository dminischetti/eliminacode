<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<meta name="theme-color" content="#F3EFE5">
<title>Accesso banco carni — {{ config('app.name') }}</title>
<style>
    :root { --tile:#F3EFE5; --ink:#18261E; --steel:#657068; --line:#D8D0C0; --insegna:#9C352C; --paper:#FFFCF6; }
    * { box-sizing: border-box; }
    body {
        margin: 0; min-height: 100vh; background: var(--tile); color: var(--ink);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        display: flex; align-items: center; justify-content: center; padding: 1.5rem;
    }
    form { width: 100%; max-width: 380px; }
    .marchio { margin-bottom: 1.5rem; }
    h1 { font-size: 1.35rem; margin: 0; letter-spacing: -0.01em; }
    .luogo { margin: 0.3rem 0 0; color: var(--steel); font-size: 0.85rem; letter-spacing: 0.08em; text-transform: uppercase; }
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
    <div class="marchio">
        <h1>{{ config('app.name') }}</h1>
        <p class="luogo">Banco carni · {{ config('queue_shop.location') }}</p>
    </div>

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
