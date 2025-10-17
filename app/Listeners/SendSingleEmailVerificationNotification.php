<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Cache;

class SendSingleEmailVerificationNotification
{
    /**
     * Lida com o evento de registro de usuário.
     */
    public function handle(Registered $event): void
    {
        // Garante que estamos lidando com um usuário que precisa verificar o e-mail
        // e que o e-mail ainda não foi verificado.
        if ($event->user instanceof MustVerifyEmail && ! $event->user->hasVerifiedEmail()) {

            /** @var User $user */
            $user = $event->user;

            // Cria uma chave de cache única para este usuário.
            $cacheKey = 'verification_email_sent_'.$user->id;

            // **LÓGICA DE BLOQUEIO DE CACHE (CACHE LOCK):**
            // Se a chave NÃO existir no cache, significa que o e-mail ainda não foi enviado.
            if (! Cache::has($cacheKey)) {
                // Envia a notificação de verificação de e-mail.
                $event->user->sendEmailVerificationNotification();

                // Coloca a chave no cache por 5 minutos (300 segundos).
                // Se este evento for disparado novamente dentro de 5 minutos,
                // o Cache::has($cacheKey) será verdadeiro, e o código de envio não será executado.
                Cache::put($cacheKey, true, 300);
            }
        }
    }
}
