<?php

namespace App\Services;

use App\Models\QueueDay;

/**
 * §47 - Unico punto in cui vive l'eleggibilita' WhatsApp.
 *
 * Esiste gia' in Fase 1, vuoto, solo per avere un unico call site fin da
 * subito: PROSSIMO e CORREGGI NUMERO la chiamano entrambi, quindi in Fase 2
 * la logica nasce in un posto solo e non puo' divergere fra i due percorsi.
 *
 * Viene invocata DENTRO le transazioni di coda (§90/§91): non deve mai
 * contenere chiamate di rete. Il suo compito in Fase 2 sara' soltanto
 * creare o riarmare righe in notification_outbox.
 */
class NotificationService
{
    public function evaluate(QueueDay $day): void
    {
        if (! config('queue_shop.whatsapp_enabled')) {
            return;
        }

        // Fase 2, passi 11-15:
        //   - selezionare i ticket eligible secondo §59
        //     (aperta, number > current, number - current <= alert_ahead,
        //      associato, non ancora notificato, dentro la finestra 24h);
        //   - saltare quelli con notification_sent_at valorizzato;
        //   - creare l'outbox pending se assente;
        //   - riportare a pending una outbox obsolete (§63);
        //   - non toccare pending / processing / sent.
    }
}
