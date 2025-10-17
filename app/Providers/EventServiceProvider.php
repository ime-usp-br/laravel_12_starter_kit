<?php

namespace App\Providers;

use App\Listeners\LogEmailFailedListener;
use App\Listeners\LogEmailSendingListener;
use App\Listeners\LogEmailSentListener;
use App\Listeners\MarkEmailAsVerifiedAfterSenhaUnicaLogin;
use App\Listeners\SendSingleEmailVerificationNotification;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Os mapeamentos de ouvinte de evento para a aplicação.
     * Deixamos vazio para ter controle manual total.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    /**
     * Registra quaisquer eventos para sua aplicação.
     */
    public function boot(): void
    {
        // Usamos o callback 'booted' para garantir que todos os providers já foram carregados.
        $this->app->booted(function () {
            // Limpa quaisquer listeners existentes para o evento Registered.
            Event::forget(Registered::class);

            // Registra MANUALMENTE apenas o nosso listener customizado.
            Event::listen(
                Registered::class,
                SendSingleEmailVerificationNotification::class
            );
        });
    }

    /**
     * Determina se eventos e listeners devem ser descobertos automaticamente.
     * Retornamos true para permitir o auto-discovery dos outros listeners.
     */
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }

    /**
     * Obtém os diretórios que devem ser usados para descobrir eventos.
     */
    protected function discoverEventsWithin(): array
    {
        return [
            $this->app->path('Listeners'),
        ];
    }
}
