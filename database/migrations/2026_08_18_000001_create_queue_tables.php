<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // §8 - QueueDay. UNIQUE(business_date) e' cio' che rende sicura
        // la creazione automatica concorrente della giornata (§10).
        Schema::create('queue_days', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->string('status', 16)->default('open');
            $table->unsignedInteger('current_number')->default(0);
            $table->unsignedInteger('last_issued_number')->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        // Invariante 3: 0 <= current_number <= last_issued_number.
        // PostgreSQL ignores the "unsigned" modifier, so the lower bounds
        // must also be part of the database constraint.
        // SQLite (usato dai test in memoria) non supporta ADD CONSTRAINT:
        // li' l'invariante resta garantita solo dal codice.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true)) {
            DB::statement('
                ALTER TABLE queue_days
                ADD CONSTRAINT queue_days_current_not_ahead
                CHECK (
                    current_number >= 0
                    AND last_issued_number >= 0
                    AND current_number <= last_issued_number
                )
            ');
        }

        // §14 - Ticket. Nessuna colonna state: lo stato e' derivato (§15).
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_day_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('public_token', 64);
            $table->string('idempotency_key', 64);
            $table->timestamps();

            $table->unique('public_token');
            $table->unique(['queue_day_id', 'number']);
            $table->unique(['queue_day_id', 'idempotency_key']);
        });

        // §46 - Audit minimo delle correzioni manuali. Tabella di sola scrittura.
        Schema::create('queue_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('queue_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('old_current_number');
            $table->unsignedInteger('new_current_number');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_corrections');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('queue_days');
    }
};
