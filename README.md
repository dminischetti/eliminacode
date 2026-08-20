# Eliminacode · La Baita della Sceriffa

Web app Laravel 12 costruita per la coda del banco carni de La Baita della Sceriffa, a Rigopiano. Il cliente prende un numero dal proprio telefono e puo' continuare ad aspettare negli spazi all'aperto della Baita mantenendo aperta la pagina; il personale usa una dashboard autenticata per chiamare il prossimo numero, correggere il numero servito e chiudere o riaprire la distribuzione.

Il sistema gestisce esclusivamente la fila al banco. Non gestisce ingresso, prenotazioni, tavoli, ordini, pagamenti o accesso alle fornacelle.

## Ambito del pilot

Questa versione e' pronta per un test controllato su una singola istanza applicativa. Comprende:

- emissione atomica e idempotente dei ticket;
- aggiornamento automatico delle schermate cliente e staff;
- protezione contro doppio tap e modifiche concorrenti;
- audit delle correzioni manuali;
- autenticazione staff senza registrazione pubblica;
- rate limiting per browser;
- header di sicurezza e risposte dinamiche non memorizzabili;
- attivazione WhatsApp esplicita, un solo avviso e tracciamento consegna;
- outbox idempotente: Meta non puo' bloccare il comando `PROSSIMO`;
- rimozione automatica degli identificativi WhatsApp;
- controllo automatico dei prerequisiti di pubblicazione.

WhatsApp e' facoltativo e disabilitato finche' le credenziali Meta non vengono configurate. Senza WhatsApp la pagina continua a mostrare normalmente l'avanzamento. SQLite e' adatto a un solo server applicativo; per piu' istanze usare PostgreSQL o MySQL e un archivio condiviso per sessioni, cache e coda dei job.

## Requisiti

- PHP 8.2 o successivo;
- Composer 2;
- estensioni PHP `mbstring`, `PDO` e `pdo_sqlite` per la configurazione predefinita;
- SQLite, PostgreSQL, MySQL o MariaDB;
- HTTPS in produzione.

## Avvio locale

```bash
git clone https://github.com/dminischetti/eliminacode.git
cd eliminacode
composer run setup
php artisan coda:staff banco@example.com
php artisan serve
```

Il setup installa le dipendenze, crea `.env`, genera `APP_KEY`, prepara SQLite ed esegue le migrazioni.

- Clienti: `http://127.0.0.1:8000/`
- Staff: `http://127.0.0.1:8000/staff/login`
- Health check: `http://127.0.0.1:8000/up`

La business date viene calcolata dal server nel fuso `Europe/Rome`. Gli intervalli di aggiornamento si configurano con:

```dotenv
CUSTOMER_POLL_SECONDS=5
STAFF_POLL_SECONDS=2
QUEUE_LOCATION="Rigopiano · Gran Sasso"
```

## Come funziona WhatsApp

Il cliente non inserisce il telefono nel sito. Dopo aver preso il numero tocca `AVVISAMI SU WHATSAPP`; il server genera un codice monouso e apre una conversazione `wa.me` gia' compilata. L'associazione diventa attiva soltanto quando il webhook riceve il messaggio realmente inviato dal cliente.

Quando la distanza dal numero servito raggiunge `QUEUE_WARNING_THRESHOLD`, l'app crea una sola riga di outbox per quel ticket. Il click staff termina senza contattare Meta; un worker invia poi:

```text
Ci siamo quasi.
Stiamo servendo il numero 29 e tu hai il numero 31.
Torna vicino al banco carni.
```

Il messaggio descrive lo stato al momento della generazione e non promette una prenotazione. Gli stati `accepted`, `delivered`, `read` e `failed` vengono aggiornati dai webhook. Un tentativo con esito di rete ambiguo non viene ripetuto automaticamente, per evitare che lo stesso cliente riceva due avvisi.

## Verifica

Prima di pubblicare una revisione:

```bash
composer validate --strict
composer audit
vendor/bin/pint --test
node --check public/js/queue.js
node --check public/js/staff.js
composer test
```

La suite copre emissione e retry idempotenti, limiti di frequenza, avanzamento e correzione concorrenti, chiusura e riapertura, sessioni cliente stateless, header HTTP, autenticazione staff, firma e idempotenza dei webhook Meta, associazione del ticket, outbox, invio, consegna, fallimento e pulizia dei dati WhatsApp.

## Configurazione di produzione

Conserva `APP_KEY` tra tutte le pubblicazioni: cambiarla invalida cookie e dati cifrati. Un minimo `.env` per un pilot SQLite su singolo server e':

```dotenv
APP_NAME="La Baita della Sceriffa"
APP_ENV=production
APP_KEY=base64:GENERATA_UNA_SOLTA
APP_DEBUG=false
APP_URL=https://coda.example.com
LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_DATABASE=/var/lib/eliminacode/database.sqlite
DB_BUSY_TIMEOUT=5000
DB_JOURNAL_MODE=WAL
DB_SYNCHRONOUS=NORMAL
DB_TRANSACTION_MODE=IMMEDIATE

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_STORE=file
QUEUE_CONNECTION=database
QUEUE_LOCATION="Rigopiano · Gran Sasso"
```

Il document root del web server deve puntare a `public/`; non esporre la radice del repository e non usare `php artisan serve` in produzione. Il proxy o web server deve inoltrare correttamente HTTPS a Laravel. `storage/`, `bootstrap/cache/` e il file SQLite devono essere scrivibili dall'utente PHP, non dal pubblico.

## Pubblicazione del pilot

Esegui da una release versionata, con il sito eventualmente in manutenzione:

```bash
composer install --no-dev --classmap-authoritative --no-interaction
php artisan migrate --force
php artisan optimize
php artisan coda:staff banco@example.com
php artisan coda:check --production
```

Il comando `coda:check --production` deve terminare con soli `PASS`. Verifica chiave, ambiente, HTTPS, cookie sicuri, database, tabelle, account staff e permessi di scrittura.

## Staging su Laravel Cloud

Per un test pubblico gestito senza amministrare un server:

1. crea un'applicazione Laravel Cloud dal repository GitHub;
2. usa la regione gia' scelta per l'applicazione e PHP 8.4;
3. collega un database MySQL 8.4 Dev nella stessa regione (512 MiB e 5 GB bastano per il pilot);
4. usa il build command:

```bash
composer install --no-dev --classmap-authoritative --no-interaction
```

5. usa il deploy command:

```bash
php artisan migrate --force
```

6. configura l'ambiente senza ridefinire le variabili `DB_*` iniettate da Laravel Cloud:

```dotenv
APP_NAME="La Baita della Sceriffa"
APP_KEY=base64:GENERATA_UNA_SOLTA
LOG_LEVEL=warning
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
QUEUE_LOCATION="Rigopiano · Gran Sasso"
CUSTOMER_POLL_SECONDS=5
STAFF_POLL_SECONDS=2
```

Le variabili `APP_ENV`, `APP_DEBUG`, `APP_URL`, `LOG_CHANNEL` e `DB_*` vengono iniettate da Laravel Cloud e non vanno duplicate. Attiva Scale to Zero sia per l'applicazione sia per il database e imposta un limite di spesa mensile. Se WhatsApp resta disabilitato non servono altri processi.

Dopo la prima migrazione, aggiungi temporaneamente `STAFF_BOOTSTRAP_PASSWORD` ai secret dell'ambiente, ridistribuisci e avvia dalla scheda Commands:

```bash
php artisan coda:staff banco@example.com --name="Banco carni" --password-env=STAFF_BOOTSTRAP_PASSWORD
php artisan coda:check --production
```

Rimuovi subito `STAFF_BOOTSTRAP_PASSWORD` dall'ambiente e ridistribuisci. La password non viene salvata nei log del comando; nel database resta solo il relativo hash.

## Configurazione WhatsApp Cloud API

Per il test iniziale usa il numero di prova fornito da Meta. Nell'ambiente Laravel Cloud aggiungi come secret:

```dotenv
WHATSAPP_ENABLED=true
WHATSAPP_BUSINESS_NUMBER=393331234567
WHATSAPP_PHONE_NUMBER_ID=123456789
WHATSAPP_ACCESS_TOKEN=TOKEN_META
WHATSAPP_APP_SECRET=APP_SECRET_META
WHATSAPP_VERIFY_TOKEN=UNA_STRINGA_CASUALE_LUNGA
WHATSAPP_GRAPH_VERSION=v25.0
QUEUE_WARNING_THRESHOLD=2
WHATSAPP_RETENTION_DAYS=2
QUEUE_CONNECTION=database
WHATSAPP_QUEUE=whatsapp
```

`WHATSAPP_BUSINESS_NUMBER` e' il numero mostrato al cliente e deve contenere solo cifre con prefisso internazionale. `WHATSAPP_PHONE_NUMBER_ID` e' invece l'identificativo tecnico mostrato da Meta: non sono lo stesso valore. Usa la versione Graph indicata nel pannello Meta se diversa dall'esempio.

Nel pannello Meta configura:

- callback URL: `https://TUO-DOMINIO/webhooks/whatsapp`;
- verify token: lo stesso valore di `WHATSAPP_VERIFY_TOKEN`;
- sottoscrizione al campo `messages`, che include messaggi in ingresso e stati in uscita.

Nel cluster applicativo Laravel Cloud aggiungi un background process con:

```bash
php artisan queue:work database --queue=whatsapp --sleep=2 --tries=1 --timeout=20
```

Abilita anche il toggle `Scheduler`, poi salva e ridistribuisci. Il worker esegue gli invii in pochi secondi; lo scheduler lancia ogni dieci minuti il recupero dell'outbox e la pulizia dei dati. Il flusso usa un normale messaggio di servizio perche' e' il cliente ad aprire la conversazione; se l'invio cade fuori dalla finestra di assistenza consentita da Meta, viene registrato come fallito senza influire sulla coda.

Controlla infine:

```bash
php artisan coda:check --production
php artisan coda:whatsapp-recover --send-now
```

Il secondo comando e' utile solo per un test manuale controllato; nell'uso normale invia il worker.

## Backup e rollback

Prima di ogni migrazione, metti l'app in manutenzione e crea una copia consistente del database. Con SQLite:

```bash
php artisan down
sqlite3 /var/lib/eliminacode/database.sqlite ".backup '/var/backups/eliminacode-YYYYMMDD-HHMM.sqlite'"
php artisan migrate --force
php artisan up
```

Conserva anche `.env` e `APP_KEY` in un secret store. Per il rollback, rimetti l'app in manutenzione, ripristina insieme il codice della release precedente e il backup compatibile del database, svuota le cache con `php artisan optimize:clear`, riesegui `coda:check --production`, quindi riapri il traffico.

## Smoke test del pilot

Dopo una pubblicazione:

1. `GET /up` risponde `200`.
2. La homepage si apre via HTTPS e mostra nome della Baita, localita' e `Ora serviamo`.
3. Un telefono prende il numero 1; un doppio tap o retry non crea il numero 2.
4. Lo staff accede e `PROSSIMO` aggiorna sia dashboard sia telefono.
5. Una correzione viene applicata solo sullo stato corrente.
6. `Chiudi giornata` blocca i nuovi ticket; `Riapri giornata` conserva i contatori.
7. Il cliente attiva WhatsApp inviando davvero il testo precompilato; aprire e chiudere WhatsApp senza inviarlo non basta.
8. Quando mancano due numeri, la dashboard avanza subito e il messaggio appare nella chat una sola volta.
9. In `whatsapp_messages` lo stato passa almeno ad `accepted`, poi a `delivered` se Meta conferma.
10. I log non contengono numeri telefonici o errori nuovi.

Durante il test monitora spazio disco, errori HTTP, tempi di risposta e log Laravel. Esegui backup giornalieri del database e prova almeno una volta il ripristino prima di usare dati reali.

## Licenza

MIT.
