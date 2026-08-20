# Eliminacode

Sistema elimina-code web essenziale per una piccola attività commerciale, costruito con Laravel 12.

## Requisiti

- PHP 8.2 o successivo
- Composer 2
- Estensioni PHP `mbstring`, `PDO` e il driver del database scelto
- SQLite, MySQL/MariaDB o PostgreSQL

## Installazione locale

```bash
git clone https://github.com/dminischetti/eliminacode.git
cd eliminacode
composer run setup
php artisan coda:staff banco@example.com
php artisan serve
```

Il comando `composer run setup` installa le dipendenze, crea `.env`, genera la chiave applicativa, prepara SQLite ed esegue le migrazioni.

Apri:

- Pagina clienti: `http://127.0.0.1:8000/`
- Pagina staff: `http://127.0.0.1:8000/staff/login`
- Controllo di salute: `http://127.0.0.1:8000/up`

## Test

```bash
composer test
```

I test verificano l'emissione idempotente dei ticket, l'avanzamento sicuro della coda, la correzione manuale, la chiusura e riapertura della giornata e l'autenticazione staff.

## Configurazione

Le impostazioni specifiche si trovano in `config/queue_shop.php` e possono essere sovrascritte in `.env`:

```dotenv
CUSTOMER_POLL_SECONDS=5
STAFF_POLL_SECONDS=2
WHATSAPP_ENABLED=false
```

La business date viene sempre calcolata sul server nel fuso `Europe/Rome`.

## Pubblicazione

Il document root del server web deve puntare alla cartella `public/`. Prima di pubblicare:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan coda:staff banco@example.com
```

Imposta almeno questi valori nell'ambiente di produzione:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
SESSION_SECURE_COOKIE=true
```

Servi il sito esclusivamente tramite HTTPS e assicurati che `storage/` e `bootstrap/cache/` siano scrivibili dal processo PHP.
