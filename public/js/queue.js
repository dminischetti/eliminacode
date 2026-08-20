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
    var whatsappSending = false;
    var syncing = false;
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

    function marchio() {
        return '<header class="marchio">' +
            '<p class="marchio__nome">' + esc(boot.venue_name) + '</p>' +
            '<p class="marchio__luogo">' + esc(boot.venue_location) + '</p>' +
        '</header>';
    }

    function richiesta(url, options) {
        var controller = window.AbortController ? new AbortController() : null;
        var timer = controller ? setTimeout(function () { controller.abort(); }, 15000) : null;
        var config = options || {};

        if (controller) { config.signal = controller.signal; }

        return fetch(url, config).then(function (response) {
            if (timer) { clearTimeout(timer); }
            return response;
        }, function (error) {
            if (timer) { clearTimeout(timer); }
            throw error;
        });
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
        app.innerHTML = marchio() + html;

        var buttons = app.querySelectorAll('[data-azione]');
        Array.prototype.forEach.call(buttons, function (button) {
            button.addEventListener('click', function () {
                if (button.getAttribute('data-azione') === 'whatsapp') {
                    attivaWhatsapp(button);
                    return;
                }

                button.disabled = true;          /* solo UX: la sicurezza sta nella key */
                nuovaIntenzione();
            });
        });
    }

    function piede() {
        if (!lastSync) { return '<div class="piede">' + privacy() + '</div>'; }

        var vecchio = (Date.now() - lastSync.getTime()) > (pollMs * 4);
        var ora = lastSync.toTimeString().slice(0, 5);

        return '<div class="piede' + (vecchio ? ' piede--vecchio' : '') + '">' +
               '<p>' + (vecchio ? 'Non aggiornato dalle ' + ora + '. Controlla la connessione.'
                                : 'Ultimo aggiornamento ' + ora) + '</p>' +
               privacy() +
               '</div>';
    }

    function privacy() {
        return '<details class="privacy">' +
            '<summary>Privacy</summary>' +
            '<p>Nessun account richiesto. L\'avviso e\' facoltativo: il tuo identificativo WhatsApp viene usato solo per questo turno, mai per marketing, e viene eliminato automaticamente.</p>' +
        '</details>';
    }

    function whatsapp(d) {
        if (!d.whatsapp || d.whatsapp.status === 'unavailable') { return ''; }

        if (d.whatsapp.status === 'active') {
            return '<div class="whatsapp-stato" role="status">Avviso WhatsApp attivo</div>' +
                '<p class="consenso">Riceverai un solo messaggio quando il turno si avvicina.</p>';
        }

        return '<button class="azione azione--whatsapp" data-azione="whatsapp">AVVISAMI SU WHATSAPP</button>' +
            '<p class="consenso">Riceverai solo messaggi relativi a questo turno.</p>' +
            '<p class="esito" data-whatsapp-esito aria-live="polite"></p>';
    }

    function schermoHome(d) {
        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Ora serviamo</p>' +
                '<p class="numero">' + esc(d.current_number) + '</p>' +
                '<p class="nota">Prendi il numero e tieni aperta questa pagina. Si aggiorna da sola.</p>' +
            '</div>' +
            '<button class="azione" data-azione="nuovo">PRENDI IL NUMERO</button>' +
            piede()
        );
    }

    function schermoAttesa(d) {
        var mancano = d.remaining === 1
            ? 'Manca 1 numero prima del tuo'
            : 'Mancano ' + esc(d.remaining) + ' numeri prima del tuo';

        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Il tuo numero</p>' +
                '<p class="numero">' + esc(d.ticket_number) + '</p>' +
                '<p class="riga">Stiamo servendo il ' + esc(d.current_number) + '</p>' +
                '<p class="riga riga--forte">' + mancano + '</p>' +
                '<p class="nota">Puoi aspettare dove preferisci. Tieni aperta questa pagina.</p>' +
            '</div>' +
            whatsapp(d) +
            piede()
        );
    }

    function schermoTurno(d) {
        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Tocca a te</p>' +
                '<p class="numero pulsa">' + esc(d.ticket_number) + '</p>' +
                '<p class="riga riga--forte">Vai al banco carni.</p>' +
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
            '<button class="azione" data-azione="nuovo">PRENDI UN ALTRO NUMERO</button>' +
            piede()
        );
    }

    function schermoChiuso() {
        view(
            '<div class="screen__body">' +
                '<p class="eyebrow">Distribuzione numeri chiusa</p>' +
                '<p class="riga">Per informazioni, rivolgiti al personale della Baita.</p>' +
            '</div>' +
            piede()
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

        richiesta('/api/tickets', {
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

    function attivaWhatsapp(button) {
        if (whatsappSending) { return; }

        var token = store.getItem(K.token);
        if (!token) { return; }

        whatsappSending = true;
        button.disabled = true;
        var esito = app.querySelector('[data-whatsapp-esito]');
        if (esito) { esito.textContent = 'Apro WhatsApp...'; }

        richiesta('/api/tickets/' + encodeURIComponent(token) + '/whatsapp-link', {
            method: 'POST',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (data) {
                if (!r.ok) {
                    var err = new Error(data.message || 'Non e\' stato possibile aprire WhatsApp.');
                    err.messaggio = data.message;
                    throw err;
                }
                return data;
            });
        }).then(function (data) {
            whatsappSending = false;

            if (data.status === 'active') {
                aggiorna();
                return;
            }

            if (data.url) {
                window.location.assign(data.url);
                return;
            }

            throw new Error('Link WhatsApp non disponibile.');
        }).catch(function (err) {
            whatsappSending = false;
            button.disabled = false;
            if (esito) {
                esito.textContent = err.messaggio || err.message || 'Riprova tra poco.';
            }
        });
    }

    /* ---------------------------------------------------------------- */

    function aggiorna() {
        if (sending || whatsappSending || syncing) { return; }

        syncing = true;

        var token = store.getItem(K.token);
        var url = token
            ? '/api/tickets/' + encodeURIComponent(token) + '/status'
            : '/api/queue';

        richiesta(url, { cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 404) { forgetTicket(); return null; }
                if (!r.ok) { throw new Error('rete'); }
                return r.json();
            })
            .then(function (d) {
                if (!d) {
                    syncing = false;
                    return aggiorna();
                }

                lastSync = new Date();

                if (d.today && d.today !== today) {
                    today = d.today;
                    purgeStale();
                }

                /* Ticket di una giornata precedente: si riparte dalla home. */
                if (d.business_date && d.today && d.business_date !== d.today) {
                    forgetTicket();
                    syncing = false;
                    return aggiorna();
                }

                syncing = false;
                render(d);
            })
            .catch(function () {
                syncing = false;
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
