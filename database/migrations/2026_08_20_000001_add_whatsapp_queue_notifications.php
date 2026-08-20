<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('whatsapp_association_token_hash', 64)->nullable()->unique();
            $table->text('whatsapp_recipient')->nullable();
            $table->timestamp('whatsapp_association_expires_at')->nullable();
            $table->timestamp('whatsapp_enabled_at')->nullable();
            $table->timestamp('whatsapp_identifier_purged_at')->nullable();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('logical_type', 32);
            $table->string('direction', 16)->default('outbound');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('current_number_snapshot');
            $table->text('body');
            $table->string('meta_message_id', 191)->nullable()->unique();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 128)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'logical_type']);
            $table->index(['status', 'available_at']);
        });

        // Il payload completo non viene conservato: basta una chiave tecnica
        // per rendere idempotenti i retry di Meta senza trattenere altri dati.
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 191)->unique();
            $table->string('event_type', 32);
            $table->timestamp('processed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_messages');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['whatsapp_association_token_hash']);
            $table->dropColumn([
                'whatsapp_association_token_hash',
                'whatsapp_recipient',
                'whatsapp_association_expires_at',
                'whatsapp_enabled_at',
                'whatsapp_identifier_purged_at',
            ]);
        });
    }
};
