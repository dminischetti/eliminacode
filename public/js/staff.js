/*
 * Eliminacode - dashboard banco.
 *
 * Regola principale: il pulsante non deve mai mentire. Se non riusciamo a
 * parlare con il server, PROSSIMO si disabilita invece di far credere al
 * macellaio che la coda sia avanzata.
 */
(function () {
    'use strict';

    var stato = window.__BANCO__ || {};
    var pollMs = Math.max(1, stato.poll_seconds || 2) * 1000;
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    var elCorpo = document.getElementById('corpo');
    var elComandi = document.getElementById('comandi');
    var elMessaggio = document.getElementById('messaggio');
    var elIntestazione = document.getElementById('intestazione');

    var inFlight = false;
    var syncing = false;
    var offline = false;
    var messaggio = null;
    var messaggioTimer = null;
    var correzioneAperta = false;

    /* ---------------------------------------------------------------- */

    function esc(v) {
        return String(v === undefined || v === null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function avvisa(testo, allarme) {
        messaggio = { testo: testo, allarme: !!allarme };
        disegna();
        if (messaggioTimer) { clearTimeout(messaggioTimer); }
        messaggioTimer = setTimeout(function () { messaggio = null; disegna(); }, 4000);
    }

    function chiamata(url, corpo) {
        var controller = window.AbortController ? new AbortController() : null;
        var timer = controller ? setTimeout(function () { controller.abort(); }, 10000) : null;

        return fetch(url, {
            method: corpo ? 'POST' : 'GET',
            cache: 'no-store',
            signal: controller ? controller.signal : undefined,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: corpo ? JSON.stringify(corpo) : undefined
        }).then(function (r) {
            if (timer) { clearTimeout(timer); }
            if (r.status === 401) {
                window.location.assign('/staff/login');
                throw new Error('sessione');
            }
            if (r.status === 419) {
                /* Sessione scaduta: meglio ricaricare che fingere. */
                window.location.reload();
                throw new Error('sessione');
            }
            return r.json().catch(function () { return {}; }).then(function (data) {
                return { ok: r.ok, status: r.status, data: data };
            });
        }, function (error) {
            if (timer) { clearTimeout(timer); }
            throw error;
        });
    }

    /* ---------------------------------------------------------------- */

    function disegna() {
        var chiusa = stato.queue_status === 'closed';

        elIntestazione.textContent = (stato.venue_name || 'La Baita della Sceriffa') +
            ' · Banco carni · ' + (stato.business_date || '');

        if (chiusa) {
            elCorpo.innerHTML =
                '<p class="eyebrow">Coda chiusa</p>' +
                '<p class="numero">' + esc(stato.current_number) + '</p>' +
                '<p class="riga">Ultimo numero servito</p>';

            elComandi.innerHTML =
                '<button class="prossimo" data-azione="riapri">RIAPRI GIORNATA</button>';
        } else {
            elCorpo.innerHTML =
                '<p class="eyebrow">Ora serviamo</p>' +
                '<p class="numero">' + esc(stato.current_number) + '</p>' +
                '<p class="riga">Ultimo numero emesso: ' + esc(stato.last_issued_number) + '</p>';

            var bloccato = offline || inFlight || !stato.has_waiting;

            elComandi.innerHTML =
                '<button class="prossimo" data-azione="prossimo"' + (bloccato ? ' disabled' : '') + '>' +
                    (stato.has_waiting ? 'PROSSIMO' : 'NESSUN NUMERO IN ATTESA') +
                '</button>' +
                '<details class="altre"' + (correzioneAperta ? ' open' : '') + '>' +
                    '<summary>Altre azioni</summary>' +
                    pannelloCorrezione() +
                    '<button class="secondaria secondaria--rossa" data-azione="chiudi">Chiudi giornata</button>' +
                '</details>';
        }

        elMessaggio.innerHTML = messaggioHtml();
        collega();
    }

    function pannelloCorrezione() {
        return '' +
            '<div class="correzione">' +
                '<p class="riga">Correggi il numero servito</p>' +
                '<div class="stepper">' +
                    '<button type="button" data-passo="-1" aria-label="Diminuisci">−</button>' +
                    '<input id="correzione" type="number" inputmode="numeric" ' +
                           'min="0" max="' + esc(stato.last_issued_number) + '" ' +
                           'value="' + esc(stato.current_number) + '">' +
                    '<button type="button" data-passo="1" aria-label="Aumenta">+</button>' +
                '</div>' +
                '<button class="secondaria" data-azione="correggi">CONFERMA CORREZIONE</button>' +
            '</div>';
    }

    function messaggioHtml() {
        if (offline) {
            return '<p class="avviso avviso--allarme">Connessione persa. ' +
                   'Verifica la rete prima di avanzare la coda.</p>';
        }
        if (messaggio) {
            return '<p class="avviso' + (messaggio.allarme ? ' avviso--allarme' : '') + '">' +
                   esc(messaggio.testo) + '</p>';
        }
        if (stato.whatsapp_failed_count > 0) {
            return '<p class="avviso avviso--whatsapp">' +
                   esc(stato.whatsapp_failed_count) +
                   (stato.whatsapp_failed_count === 1
                       ? ' avviso WhatsApp da verificare. '
                       : ' avvisi WhatsApp da verificare. ') +
                   'La coda continua a funzionare.</p>';
        }
        return '';
    }

    function collega() {
        var dettagli = elComandi.querySelector('details');
        if (dettagli) {
            dettagli.addEventListener('toggle', function () { correzioneAperta = dettagli.open; });
        }

        Array.prototype.forEach.call(elComandi.querySelectorAll('[data-passo]'), function (b) {
            b.addEventListener('click', function () {
                var campo = document.getElementById('correzione');
                if (!campo) { return; }
                var valore = parseInt(campo.value, 10);
                if (isNaN(valore)) { valore = stato.current_number; }
                valore += parseInt(b.getAttribute('data-passo'), 10);
                campo.value = Math.min(stato.last_issued_number, Math.max(0, valore));
            });
        });

        Array.prototype.forEach.call(elComandi.querySelectorAll('[data-azione]'), function (b) {
            b.addEventListener('click', function () {
                var azione = b.getAttribute('data-azione');
                if (azione === 'prossimo') { prossimo(b); }
                if (azione === 'correggi') { correggi(); }
                if (azione === 'chiudi') { chiudi(); }
                if (azione === 'riapri') { riapri(); }
            });
        });
    }

    /* ---------------------------------------------------------------- */

    function prossimo(bottone) {
        if (inFlight || offline) { return; }
        inFlight = true;
        bottone.disabled = true;   /* solo UX: la protezione vera e' sul server */

        chiamata('/api/staff/queue/next', {
            expected_current_number: stato.current_number
        }).then(function (r) {
            inFlight = false;

            if (r.status === 409) {
                /* Doppio tap o secondo dispositivo: risincronizza e basta. */
                if (r.data.queue) { stato = aggiornaStato(r.data.queue); }
                avvisa(r.data.message || 'La coda e\' cambiata.');
                return;
            }

            if (!r.ok) { avvisa('Operazione non riuscita.', true); disegna(); return; }

            stato = aggiornaStato(r.data);
            disegna();
        }).catch(function () {
            inFlight = false;
            segnalaOffline();
        });
    }

    function correggi() {
        var campo = document.getElementById('correzione');
        if (!campo || inFlight || offline) { return; }

        var nuovo = parseInt(campo.value, 10);
        if (isNaN(nuovo)) { return; }

        if (nuovo === stato.current_number) {
            avvisa('Il numero servito e\' gia\' ' + nuovo + '.');
            return;
        }

        var conferma = 'Vuoi cambiare il numero servito da ' +
                       stato.current_number + ' a ' + nuovo + '?';
        if (!window.confirm(conferma)) { return; }

        inFlight = true;

        chiamata('/api/staff/queue/correct', {
            new_current_number: nuovo,
            expected_current_number: stato.current_number
        })
            .then(function (r) {
                inFlight = false;

                if (!r.ok) {
                    if (r.data.queue) { stato = aggiornaStato(r.data.queue); }
                    avvisa(r.data.message || 'Correzione non valida.', true);
                    disegna();
                    return;
                }

                stato = aggiornaStato(r.data);
                correzioneAperta = false;
                avvisa('Numero servito aggiornato.');
            })
            .catch(function () { inFlight = false; segnalaOffline(); });
    }

    function chiudi() {
        if (inFlight || offline) { return; }
        if (!window.confirm('Chiudere la coda di oggi?')) { return; }

        inFlight = true;
        disegna();
        chiamata('/api/staff/queue/close', {})
            .then(function (r) {
                inFlight = false;
                if (!r.ok) { avvisa(r.data.message || 'Operazione non riuscita.', true); return; }
                stato = aggiornaStato(r.data);
                disegna();
            })
            .catch(function () { inFlight = false; segnalaOffline(); });
    }

    function riapri() {
        if (inFlight || offline) { return; }
        inFlight = true;
        disegna();
        chiamata('/api/staff/queue/reopen', {})
            .then(function (r) {
                inFlight = false;
                if (r.ok) {
                    stato = aggiornaStato(r.data);
                    avvisa('Coda riaperta dal numero ' + stato.current_number + '.');
                    return;
                }
                avvisa(r.data.message || 'Operazione non riuscita.', true);
            })
            .catch(function () { inFlight = false; segnalaOffline(); });
    }

    /* ---------------------------------------------------------------- */

    function aggiornaStato(dati) {
        offline = false;
        return {
            business_date: dati.business_date,
            queue_status: dati.queue_status,
            current_number: dati.current_number,
            last_issued_number: dati.last_issued_number,
            has_waiting: dati.has_waiting,
            whatsapp_failed_count: dati.whatsapp_failed_count || 0,
            poll_seconds: stato.poll_seconds,
            venue_name: stato.venue_name
        };
    }

    function segnalaOffline() {
        offline = true;
        disegna();
    }

    function aggiorna() {
        if (inFlight || syncing) { return; }

        syncing = true;
        chiamata('/api/staff/queue')
            .then(function (r) {
                syncing = false;
                if (!r.ok) { segnalaOffline(); return; }
                var eraOffline = offline;
                stato = aggiornaStato(r.data);
                if (eraOffline) { avvisa('Connessione ripristinata.'); }
                disegna();
            })
            .catch(function () { syncing = false; segnalaOffline(); });
    }

    /* ---------------------------------------------------------------- */

    disegna();
    setInterval(aggiorna, pollMs);

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') { aggiorna(); }
    });
    window.addEventListener('online', aggiorna);
})();
