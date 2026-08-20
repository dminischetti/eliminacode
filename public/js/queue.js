/*
 * Eliminacode - logica cliente.
 *
 * Due regole guidano tutto questo file:
 *
 *  1. La business date arriva sempre dal server. Il telefono puo' avere
 *     fuso o orologio sbagliati, quindi non calcoliamo mai la data qui.
 *
 *  2. Una idempotency key rappresenta UN'INTENZIONE di prendere il numero,
 *     non una richiesta HTTP. Tutti i retry della stessa intenzione usano
 *     la stessa key; una key nuova nasce solo quando il cliente decide
 *     davvero di prendere un altro numero.
 */
(function () {
    'use strict';

    var boot = window.__CODA__ || {};

    var K = {
        pendingKey: 'coda.pending_key',
        pendingDate: 'coda.pending_date',
        token: 'coda.token',
        tokenDate: 'coda.token_date'
    };

    /* localStorage puo' lanciare in navigazione privata: si degrada in memoria. */
    var store = (function () {
        try {
            window.localStorage.setItem('coda.probe', '1');
            window.localStorage.removeItem('coda.probe');
            return window.localStorage;
        } catch (e) {
            var mem = {};
            return {
                getItem: function (k) { return mem.hasOwnProperty(k) ? mem[k] : null; },
                setItem: function (k, v) { mem[k] = String(v); },
                removeItem: function (k) { delete mem[k]; }
            };
        }
    })();

    var app = document.getElementById('app');
    var today = boot.today;
    var pollMs = Math.max(2, boot.poll_seconds || 5) * 1000;
    var timer = null;
    var lastSync = null;
    var sending = false;
    var retryDelay = 2000;

    /* ---------------------------------------------------------------- */

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        var b = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(b);
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        var h = [];
        for (var i = 0; i < 16; i++) { h.push((b[i] + 0x100).toString(16).slice(1)); }
        return h.slice(0, 4).join('') + '-' + h.slice(4, 6).join('') + '-' +
               h.slice(6, 8).join('') + '-' + h.slice(8, 10).join('') + '-' +
               h.slice(10, 16).join('');
    }

    function esc(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /* Scarta pending key e ticket di una giornata precedente. */
    function purgeStale() {
        if (store.getItem(K.pendingDate) && store.getItem(K.pendingDate) !== today) {
            store.removeItem(K.pendingKey);
            store.removeItem(K.pendingDate);
        }
        if (store.getItem(K.tokenDate) && store.getItem(K.tokenDate) !== today) {
            forgetTicket();
        }
    }

    function forgetTicket() {
        store.removeItem(K.token);
        store.removeItem(K.tokenDate);
    }

    /* ---------------------------------------------------------------- */

    function view(html, modifier) {
        app.className = 'screen' + (modifier ? ' ' + modifier : '');
        app.innerHTML = html;

        var button = app.querySelector('[data-azione]');
        if (button) {
            button.addEventListener('click', function () {
                button.disabled = true;          /* solo UX: la sicurezza sta nella key */
                nuovaIntenzione();
            });
        }
    }

    function piede() {
        if (!lastSync) { return '<p class="piede"></p>'; }

        var vecchio = (Date.now() - lastSync.getTime()) > (pollMs * 4);
        var ora = lastSync.toTimeString().slice(0, 5);

        return '<p class="piede' + (vecchio ? ' piede--vecchio' : '') + '">' +
               (vecchio ? 'Non aggiornato dalle ' + ora + '. Controlla la connessione.'
                        : 'Ultimo aggiornamento ' + ora) +
               '</p>';
    }

    function schermoHome(d) {
        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Ora serviamo</p>' +
                '<p class="numero">' + esc(d.current_number) + '</p>' +
            '</div>' +
            '<button class="azione" data-azione="nuovo">PRENDI UN NUMERO</button>' +
            piede()
        );
    }

    function schermoAttesa(d) {
        var mancano = d.remaining === 1
            ? 'Manca 1 numero prima del tuo'
            : 'Mancano ' + esc(d.remaining) + ' numeri prima del tuo';

        var whatsapp = d.whatsapp_enabled
            ? (d.whatsapp_associated
                ? '<p class="avviso">Avviso WhatsApp attivo</p>'
                : '<button class="azione" data-whatsapp="1">AVVISAMI SU WHATSAPP</button>')
            : '';

        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Il tuo numero</p>' +
                '<p class="numero">' + esc(d.ticket_number) + '</p>' +
                '<p class="riga">Stiamo servendo il ' + esc(d.current_number) + '</p>' +
                '<p class="riga riga--forte">' + mancano + '</p>' +
            '</div>' +
            whatsapp +
            piede()
        );
    }

    function schermoTurno(d) {
        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Tocca a te</p>' +
                '<p class="numero pulsa">' + esc(d.ticket_number) + '</p>' +
                '<p class="riga riga--forte">Vai al banco.</p>' +
            '</div>' +
            piede(),
            'screen--turno'
        );
    }

    function schermoPassato(d) {
        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Il tuo numero e\' gia\' stato chiamato</p>' +
                '<p class="numero numero--piccolo">' + esc(d.ticket_number) + '</p>' +
                '<p class="riga">Rivolgiti al personale al banco.</p>' +
            '</div>' +
            '<button class="azione" data-azione="nuovo">PRENDI UN NUOVO NUMERO</button>' +
            piede()
        );
    }

    function schermoChiuso() {
        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Siamo chiusi</p>' +
                '<p class="riga">La coda di oggi e\' terminata.</p>' +
            '</div>'
        );
    }

    function schermoInvio(riprova) {
        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Un attimo</p>' +
                '<p class="riga">' +
                    (riprova ? 'Connessione lenta. Stiamo riprovando, non perderai il numero.'
                             : 'Stiamo prendendo il tuo numero.') +
                '</p>' +
            '</div>'
        );
    }

    function schermoErrore(messaggio) {
        view(
            '<div class="screen__body">' +
                '<p class="avviso">' + esc(messaggio) + '</p>' +
            '</div>' +
            '<button class="azione" data-azione="nuovo">RIPROVA</button>'
        );
    }

    /* Sceglie lo schermo dai dati. Unico punto di decisione. */
    function render(d) {
        if (d.queue_status === 'closed') { return schermoChiuso(); }
        if (!d.ticket_number) { return schermoHome(d); }

        if (d.ticket_state === 'called') { return schermoTurno(d); }
        if (d.ticket_state === 'passed') { return schermoPassato(d); }
        return schermoAttesa(d);
    }

    /* ---------------------------------------------------------------- */

    function nuovaIntenzione() {
        if (sending) { return; }
        var key = uuid();
        store.setItem(K.pendingKey, key);
        store.setItem(K.pendingDate, today);
        retryDelay = 2000;
        invia(key);
    }

    function invia(key) {
        if (sending) { return; }
        sending = true;
        schermoInvio(false);

        fetch('/api/tickets', {
            method: 'POST',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'Idempotency-Key': key,
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (r) {
            if (r.status === 409 || r.status === 422 || r.status === 429) {
                return r.json().catch(function () { return {}; }).then(function (data) {
                    var err = new Error('definitivo');
                    err.definitivo = true;
                    err.messaggio = data.message || 'Non e\' stato possibile prendere il numero.';
                    throw err;
                });
            }
            if (!r.ok) { throw new Error('rete'); }
            return r.json();
        }).then(function (d) {
            sending = false;
            retryDelay = 2000;
            store.setItem(K.token, d.public_token);
            store.setItem(K.tokenDate, d.business_date);
            store.removeItem(K.pendingKey);
            store.removeItem(K.pendingDate);
            lastSync = new Date();
            render(d);
            avvia();
        }).catch(function (err) {
            sending = false;

            if (err && err.definitivo) {
                store.removeItem(K.pendingKey);
                store.removeItem(K.pendingDate);
                schermoErrore(err.messaggio);
                setTimeout(aggiorna, 3000);
                return;
            }

            /* La key resta salvata: si riprova la stessa intenzione. */
            schermoInvio(true);
            setTimeout(function () { invia(key); }, retryDelay);
            retryDelay = Math.min(retryDelay * 2, 30000);
        });
    }

    /* ---------------------------------------------------------------- */

    function aggiorna() {
        if (sending) { return; }

        var token = store.getItem(K.token);
        var url = token
            ? '/api/tickets/' + encodeURIComponent(token) + '/status'
            : '/api/queue';

        fetch(url, { cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 404) { forgetTicket(); return null; }
                if (!r.ok) { throw new Error('rete'); }
                return r.json();
            })
            .then(function (d) {
                if (!d) { return aggiorna(); }

                lastSync = new Date();

                if (d.today && d.today !== today) {
                    today = d.today;
                    purgeStale();
                }

                /* Ticket di una giornata precedente: si riparte dalla home. */
                if (d.business_date && d.today && d.business_date !== d.today) {
                    forgetTicket();
                    return aggiorna();
                }

                render(d);
            })
            .catch(function () {
                /* Offline: si tiene l'ultimo stato noto e si segnala l'ora. */
                var button = app.querySelector('[data-azione]');
                if (!button && lastSync) {
                    var footer = app.querySelector('.piede');
                    if (footer) { footer.outerHTML = piede(); }
                }
            });
    }

    function avvia() {
        if (timer) { clearInterval(timer); }
        timer = setInterval(aggiorna, pollMs);
    }

    /* ---------------------------------------------------------------- */

    purgeStale();

    if (store.getItem(K.pendingKey)) {
        /* Richiesta interrotta prima della risposta: stessa key, mai una nuova. */
        invia(store.getItem(K.pendingKey));
    } else if (store.getItem(K.token)) {
        aggiorna();
        avvia();
    } else {
        lastSync = new Date();
        render(boot);
        avvia();
    }

    /* Riprendere subito quando lo schermo torna acceso o la rete ritorna. */
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') { aggiorna(); }
    });
    window.addEventListener('online', aggiorna);
})();
