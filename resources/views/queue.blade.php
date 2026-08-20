<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#EEF1F0">
<meta name="robots" content="noindex">
<title>{{ config('app.name') }} — Coda</title>

{{--
    Nessun font esterno e nessuna CDN, di proposito: la pagina deve aprirsi
    sulla Wi-Fi del negozio anche quando fuori non si prende niente. La
    personalita' viene dalla scala del numero, non da un webfont scaricato.
--}}
<style>
    :root {
        --tile:     #EEF1F0;  /* bianco freddo, come le piastrelle del banco */
        --ink:      #16181A;
        --steel:    #5C6470;
        --line:     #D6DBDA;
        --insegna:  #9E1B1B;  /* il rosso dell'insegna, usato una volta sola */
        --paper:    #FFFFFF;
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

    .piede {
        margin-top: 1.25rem;
        text-align: center;
        font-size: 0.9rem;
        color: var(--steel);
        min-height: 1.2em;
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
    .screen--turno .riga {
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
