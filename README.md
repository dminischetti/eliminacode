# Eliminacode

Eliminacode e' una web app Laravel 12 per gestire una singola coda giornaliera in una piccola attivita'. Il cliente prende un numero dal proprio telefono e vede l'avanzamento; il personale usa una dashboard autenticata per chiamare il prossimo numero, correggere il numero servito e chiudere o riaprire la giornata.

## Ambito del pilot

Questa versione e' pronta per un test controllato su una singola istanza applicativa. Comprende:

- emissione atomica e idempotente dei ticket;
- aggiornamento automatico delle schermate cliente e staff;
- protezione contro doppio tap e modifiche concorrenti;
- audit delle correzioni manuali;
- autenticazione staff senza registrazione pubblica;
- rate limiting per browser;
- header di sicurezza e risposte dinamiche non memorizzabili;
- controllo automatico dei prerequisiti di pubblicazione.

Il pilot non invia notifiche WhatsApp, push, SMS o email. SQLite e' adatto a un solo server applicativo; per piu' istanze usare PostgreSQL o MySQL e un archivio condiviso per sessioni e cache.

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
```

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

La suite copre emissione e retry idempotenti, limiti di frequenza, avanzamento e correzione concorrenti, chiusura e riapertura, sessioni cliente stateless, header HTTP e autenticazione staff.

## Configurazione di produzione

Conserva `APP_KEY` tra tutte le pubblicazioni: cambiarla invalida cookie e dati cifrati. Un minimo `.env` per un pilot SQLite su singolo server e':

```dotenv
APP_NAME=Eliminacode
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
QUEUE_CONNECTION=sync
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
2. La homepage si apre via HTTPS e mostra `Ora serviamo`.
3. Un telefono prende il numero 1; un doppio tap o retry non crea il numero 2.
4. Lo staff accede e `PROSSIMO` aggiorna sia dashboard sia telefono.
5. Una correzione viene applicata solo sullo stato corrente.
6. `Chiudi giornata` blocca i nuovi ticket; `Riapri giornata` conserva i contatori.
7. I log in `storage/logs/laravel.log` non contengono errori nuovi.

Durante il test monitora spazio disco, errori HTTP, tempi di risposta e log Laravel. Esegui backup giornalieri del database e prova almeno una volta il ripristino prima di usare dati reali.

## Licenza

MIT.
