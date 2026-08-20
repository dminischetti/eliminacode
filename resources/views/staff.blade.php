<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F3EFE5">
<meta name="robots" content="noindex">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Banco carni — {{ config('app.name') }}</title>

<style>
    :root {
        --tile:    #F3EFE5;
        --ink:     #18261E;
        --steel:   #657068;
        --line:    #D8D0C0;
        --insegna: #9C352C;
        --paper:   #FFFCF6;
    }

    * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

    html, body { margin: 0; height: 100%; }

    body {
        background: var(--tile);
        color: var(--ink);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 18px;
        line-height: 1.35;
        -webkit-font-smoothing: antialiased;
        -webkit-user-select: none;
        user-select: none;
    }

    .schermo {
        min-height: 100dvh;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        padding: max(1rem, env(safe-area-inset-top)) 1.25rem max(1.25rem, env(safe-area-inset-bottom));
        max-width: 720px;
        margin: 0 auto;
    }

    .barra {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        font-size: 0.9rem;
        color: var(--steel);
        min-height: 2rem;
    }

    .barra form { margin: 0; }

    .esci {
        border: 0;
        background: none;
        color: var(--steel);
        font: inherit;
        text-decoration: underline;
        padding: 0.5rem 0;
        cursor: pointer;
    }

    .corpo {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        gap: 0.4rem;
    }

    .eyebrow {
        font-size: 0.95rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--steel);
    }

    .numero {
        font-size: clamp(6rem, 34vw, 13rem);
        font-weight: 800;
        line-height: 0.85;
        letter-spacing: -0.045em;
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum" 1;
        margin: 0.3rem 0 0.6rem;
    }

    .riga { font-size: 1.15rem; color: var(--steel); }

    /* Il pulsante dominante. Grande, in basso, raggiungibile col pollice. */
    .prossimo {
        display: block;
        width: 100%;
        min-height: 120px;
        border: 0;
        border-radius: 16px;
        background: var(--ink);
        color: var(--paper);
        font-family: inherit;
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        cursor: pointer;
    }

    .prossimo:active { transform: translateY(2px); }

    .prossimo:focus-visible { outline: 4px solid var(--insegna); outline-offset: 3px; }

    .prossimo[disabled] { opacity: 0.35; cursor: default; transform: none; }

    /* Le azioni straordinarie stanno dietro un tocco in piu', lontano da PROSSIMO. */
    .altre {
        margin-top: 1rem;
        border-top: 1px solid var(--line);
        padding-top: 0.5rem;
    }

    .altre > summary {
        list-style: none;
        cursor: pointer;
        color: var(--steel);
        font-size: 0.95rem;
        padding: 0.75rem 0;
        text-align: center;
    }

    .altre > summary::-webkit-details-marker { display: none; }

    .secondaria {
        display: block;
        width: 100%;
        min-height: 60px;
        margin-top: 0.6rem;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--paper);
        color: var(--ink);
        font-family: inherit;
        font-size: 1.05rem;
        font-weight: 600;
        cursor: pointer;
    }

    .secondaria--rossa { color: var(--insegna); border-color: rgba(158, 27, 27, 0.35); }

    .correzione {
        margin-top: 0.75rem;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--paper);
        padding: 1rem;
    }

    .stepper {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        margin: 0.75rem 0;
    }

    .stepper button {
        width: 68px;
        height: 68px;
        border: 1px solid var(--line);
        border-radius: 12px;
        background: var(--tile);
        font: inherit;
        font-size: 2rem;
        font-weight: 700;
        cursor: pointer;
    }

    .stepper input {
        width: 6rem;
        height: 68px;
        border: 1px solid var(--line);
        border-radius: 12px;
        text-align: center;
        font-family: inherit;
        font-size: 2rem;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        background: var(--paper);
        color: var(--ink);
    }

    .avviso {
        margin-top: 0.75rem;
        border-radius: 10px;
        padding: 0.8rem 1rem;
        font-size: 1rem;
        background: var(--paper);
        border: 1px solid var(--line);
        color: var(--steel);
        text-align: center;
    }

    .avviso--allarme {
        background: var(--insegna);
        border-color: var(--insegna);
        color: var(--paper);
        font-weight: 600;
    }

    .avviso--whatsapp {
        border-color: #B98516;
        color: #73510A;
        background: #FFF5D8;
        font-weight: 600;
    }

    .nascosto { display: none; }
</style>
</head>
<body>

<div class="schermo">
    <div class="barra">
        <span id="intestazione">{{ config('app.name') }} · Banco carni</span>
        <form method="POST" action="{{ route('staff.logout') }}">
            @csrf
            <button type="submit" class="esci">Esci</button>
        </form>
    </div>

    <div class="corpo" id="corpo">
        <p class="eyebrow">Ora serviamo</p>
        <p class="numero">—</p>
    </div>

    <div id="comandi"></div>
    <div id="messaggio"></div>
</div>

<script>
    window.__BANCO__ = @json($boot);
</script>
<script src="{{ asset('js/staff.js') }}?v={{ @filemtime(public_path('js/staff.js')) ?: 1 }}"></script>

</body>
</html>
