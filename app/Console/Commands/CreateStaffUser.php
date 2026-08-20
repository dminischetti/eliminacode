<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Un solo account per il negozio, creato dalla riga di comando.
 * Niente registrazione pubblica, niente reset via email: se la password
 * si perde, si rilancia questo comando.
 *
 *   php artisan coda:staff banco@macelleria.it
 */
class CreateStaffUser extends Command
{
    protected $signature = 'coda:staff {email} {--name=Banco}';

    protected $description = 'Crea o aggiorna l\'account staff del negozio';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $password = $this->secret('Password');
        $conferma = $this->secret('Conferma password');

        if ($password !== $conferma) {
            $this->error('Le due password non coincidono.');

            return self::FAILURE;
        }

        if (mb_strlen((string) $password) < 10) {
            $this->error('Usa almeno 10 caratteri.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) $this->option('name'),
                'password' => Hash::make($password),
            ]
        );

        $this->info(sprintf(
            'Account %s per %s. Accedi da /staff/login',
            $user->wasRecentlyCreated ? 'creato' : 'aggiornato',
            $email
        ));

        return self::SUCCESS;
    }
}
