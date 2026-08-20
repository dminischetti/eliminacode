<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F3EFE5">
<meta name="robots" content="noindex">
<meta name="description" content="Il tuo numero per il banco de {{ config('app.name') }}.">
<title>{{ config('app.name') }} — Coda</title>

{{--
    Nessun font esterno e nessuna CDN: la pagina deve aprirsi sulla Wi-Fi
    della Baita anche quando la rete mobile a Rigopiano non prende.
--}}
<style>
    :root {
        --tile:     #F3EFE5;
        --ink:      #18261E;
        --steel:    #657068;
        --line:     #D8D0C0;
        --bosco:    #294936;
        --insegna:  #9C352C;
        --paper:    #FFFCF6;
    }

    * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

    html, body {
        margin: 0;
        height: 100%;
    }

    body {
        background: var(--tile);
        color: var(--ink);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 18px;
        line-height: 1.35;
        -webkit-font-smoothing: antialiased;
    }

    .screen {
        min-height: 100dvh;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        padding: max(1.5rem, env(safe-area-inset-top)) 1.25rem max(1.5rem, env(safe-area-inset-bottom));
    }

    .screen__body {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        gap: 0.75rem;
    }

    .marchio {
        flex: 0 0 auto;
        text-align: center;
        padding: 0.15rem 0 0.85rem;
        color: var(--bosco);
    }

    .marchio__nome {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 800;
        letter-spacing: 0.09em;
        text-transform: uppercase;
    }

    .marchio__luogo {
        margin: 0.25rem 0 0;
        color: var(--steel);
        font-size: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .eyebrow {
        font-size: 0.95rem;
        font-weight: 600;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--steel);
    }

    .numero {
        font-size: clamp(6rem, 40vw, 15rem);
        font-weight: 800;
        line-height: 0.85;
        letter-spacing: -0.045em;
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum" 1;
        margin: 0.25rem 0;
    }

    .numero--piccolo {
        font-size: clamp(3.5rem, 22vw, 7rem);
    }

    .riga {
        font-size: 1.25rem;
        color: var(--steel);
    }

    .riga--forte {
        color: var(--ink);
        font-weight: 600;
    }

    .nota {
        max-width: 28rem;
        margin: 0.5rem auto 0;
        font-size: 1rem;
        color: var(--steel);
    }

    .azione {
        display: block;
        width: 100%;
        min-height: 76px;
        border: 0;
        border-radius: 14px;
        background: var(--ink);
        color: var(--paper);
        font-family: inherit;
        font-size: 1.4rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        padding: 1rem;
        cursor: pointer;
    }

    .azione:focus-visible {
        outline: 4px solid var(--insegna);
        outline-offset: 3px;
    }

    .azione[disabled] {
        opacity: 0.45;
        cursor: default;
    }

    .azione--whatsapp {
        margin-top: 0.75rem;
        background: #176B43;
    }

    .consenso,
    .esito {
        margin: 0.55rem auto 0;
        max-width: 32rem;
        text-align: center;
        color: var(--steel);
        font-size: 0.88rem;
    }

    .esito {
        color: var(--insegna);
        min-height: 1.2em;
    }

    .whatsapp-stato {
        border: 2px solid #176B43;
        border-radius: 14px;
        padding: 0.9rem 1rem;
        text-align: center;
        color: #176B43;
        font-weight: 750;
    }

    .piede {
        margin-top: 1.25rem;
        text-align: center;
        font-size: 0.9rem;
        color: var(--steel);
        min-height: 1.2em;
    }

    .piede p { margin: 0; }

    .privacy {
        margin-top: 0.45rem;
        color: var(--steel);
    }

    .privacy summary {
        cursor: pointer;
        text-decoration: underline;
        text-underline-offset: 0.15em;
    }

    .privacy p {
        max-width: 34rem;
        margin: 0.45rem auto 0;
        font-size: 0.82rem;
        line-height: 1.45;
    }

    .piede--vecchio {
        color: var(--insegna);
        font-weight: 600;
    }

    /*
        La firma della pagina: quando tocca al cliente lo schermo diventa
        tutto rosso. Si riconosce con un'occhiata in tasca, da lontano,
        senza leggere niente.
    */
    .screen--turno {
        background: var(--insegna);
        color: var(--paper);
    }

    .screen--turno .eyebrow,
    .screen--turno .riga,
    .screen--turno .marchio,
    .screen--turno .marchio__luogo {
        color: rgba(255, 255, 255, 0.82);
    }

    .screen--turno .azione {
        background: var(--paper);
        color: var(--insegna);
    }

    .screen--turno .piede {
        color: rgba(255, 255, 255, 0.72);
    }

    .pulsa { animation: pulsa 1.6s ease-in-out infinite; }

    @keyframes pulsa {
        0%, 100% { transform: scale(1); }
        50%      { transform: scale(1.035); }
    }

    .avviso {
        border: 1px solid var(--line);
        background: var(--paper);
        border-radius: 12px;
        padding: 0.9rem 1rem;
        font-size: 1.05rem;
        color: var(--steel);
    }

    .link {
        display: inline-block;
        margin-top: 0.35rem;
        color: var(--steel);
        font-size: 0.95rem;
    }

    @media (prefers-reduced-motion: reduce) {
        .pulsa { animation: none; }
    }
</style>
</head>
<body>

<div id="app" class="screen">
    <header class="marchio">
        <p class="marchio__nome">{{ config('app.name') }}</p>
        <p class="marchio__luogo">{{ config('queue_shop.location') }}</p>
    </header>
    <div class="screen__body">
        <p class="eyebrow">Ora serviamo</p>
        <p class="numero">—</p>
    </div>
</div>

<script>
    window.__CODA__ = @json($boot);
</script>
<script src="{{ asset('js/queue.js') }}?v={{ @filemtime(public_path('js/queue.js')) ?: 1 }}"></script>

</body>
</html>
