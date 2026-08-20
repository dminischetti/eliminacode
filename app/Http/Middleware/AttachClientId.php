<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * §27 - Tutti i clienti del negozio escono dallo stesso indirizzo NAT,
 * quindi limitare per IP significa o bloccare il sabato mattina o non
 * bloccare nulla. Assegniamo invece un identificativo casuale per browser.
 *
 * Non e' un dato personale e non identifica una persona: e' un numero
 * casuale che serve solo a distinguere una sessione dall'altra. Chi vuole
 * aggirarlo puo' cancellare i cookie, ed e' accettato.
 */
class AttachClientId
{
    public const COOKIE = 'coda_cid';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->cookie(self::COOKIE);

        if (is_string($id) && preg_match('/^[0-9a-f]{32}$/', $id)) {
            return $next($request);
        }

        $id = bin2hex(random_bytes(16));
        $request->cookies->set(self::COOKIE, $id);

        $secure = config('session.secure');

        return $next($request)->withCookie(
            cookie(
                name: self::COOKIE,
                value: $id,
                minutes: 60 * 24 * 365,
                secure: is_bool($secure) ? $secure : $request->isSecure(),
                httpOnly: true,
                sameSite: (string) config('session.same_site', 'lax'),
            )
        );
    }
}
