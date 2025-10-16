# Exemplos de Uso do Sistema de Filas

Este documento contém exemplos práticos de como usar o sistema de filas assíncronas no projeto.

## 📧 Notificações de Email Disponíveis

### 1. Reset de Senha (ResetPasswordNotification)

**Uso automático** - já integrado no modelo User:

```php
// Ao usar o sistema de reset de senha padrão do Laravel
// a notificação será enviada automaticamente de forma assíncrona
```

**Como funciona**:
- Fila: `high` (prioridade alta por ser relacionado à segurança)
- Retry: 5 tentativas com backoff progressivo (15s, 30s, 60s, 120s, 240s)
- Timeout: 15 minutos
- Token enviado expira conforme configuração em `config/auth.php`

### 2. Boas-Vindas (WelcomeNotification)

Enviar email de boas-vindas para novos usuários:

```php
use App\Notifications\WelcomeNotification;

// Após criar um novo usuário
$user = User::create([
    'name' => 'João Silva',
    'email' => 'joao@example.com',
    'password' => Hash::make('senha123'),
]);

// Enviar boas-vindas (assíncrono)
$user->notify(new WelcomeNotification());
```

**Configuração**:
- Fila: `default`
- Retry: 3 tentativas (30s, 60s, 120s)
- Timeout: 10 minutos

### 3. Notificação de Novo Usuário (UserRegisteredNotification)

Notificar admins quando um novo usuário se cadastra:

```php
use App\Notifications\UserRegisteredNotification;
use App\Models\User;

// Após registro de novo usuário
$newUser = User::create([...]);

// Notificar todos os admins
$admins = User::role('Admin')->get();
foreach ($admins as $admin) {
    $admin->notify(new UserRegisteredNotification());
}

// Ou usando Notification facade
Notification::send($admins, new UserRegisteredNotification());
```

**Configuração**:
- Fila: `high` (notificação importante)
- Retry: 3 tentativas (15s, 30s, 60s)
- Timeout: 5 minutos

### 4. Alertas do Sistema (SystemAlertNotification)

Enviar alertas sobre eventos importantes do sistema:

```php
use App\Notifications\SystemAlertNotification;

// Alerta crítico
$admin->notify(new SystemAlertNotification(
    'critical',
    'Sistema com alto uso de memória',
    [
        'memory_usage' => '95%',
        'server' => 'web-01',
        'timestamp' => now()->toISOString()
    ]
));

// Alerta de warning
$admin->notify(new SystemAlertNotification(
    'warning',
    'Backup concluído com avisos',
    [
        'backup_size' => '2.5GB',
        'warnings' => 3,
        'duration' => '45 minutes'
    ]
));

// Alerta informativo
$admin->notify(new SystemAlertNotification(
    'info',
    'Deploy realizado com sucesso',
    [
        'version' => '2.1.0',
        'environment' => 'production'
    ]
));
```

**Configuração**:
- Fila: `high` para critical, `default` para outros
- Retry: 5 tentativas para critical, 5 para outros
- Timeout: 30 minutos para critical, 15 para outros
- Canais: `mail` e `database`

## 🔧 Exemplos de Uso Avançado

### Delay no Envio

```php
// Enviar email de boas-vindas após 10 minutos
$user->notify((new WelcomeNotification())->delay(now()->addMinutes(10)));

// Enviar após 1 hora
$user->notify((new WelcomeNotification())->delay(now()->addHour()));
```

### Trocar Fila Dinamicamente

```php
// Forçar fila low para notificação não urgente
$user->notify((new WelcomeNotification())->onQueue('low'));

// Forçar fila high para urgente
$user->notify((new WelcomeNotification())->onQueue('high'));
```

### Notificação com Callback após Sucesso

```php
use Illuminate\Support\Facades\Log;

$user->notify(
    (new WelcomeNotification())->afterCommit(function () use ($user) {
        Log::info("Welcome email queued for {$user->email}");
    })
);
```

### Enviar para Múltiplos Usuários

```php
use Illuminate\Support\Facades\Notification;

// Notificar todos os admins
$admins = User::role('Admin')->get();
Notification::send($admins, new SystemAlertNotification('info', 'Sistema atualizado'));

// Notificar usuários específicos
$users = User::whereIn('id', [1, 2, 3])->get();
Notification::send($users, new WelcomeNotification());
```

### Notificação Anônima (sem usuário)

```php
use Illuminate\Support\Facades\Notification;

// Enviar para email específico sem ter um User
Notification::route('mail', 'admin@example.com')
    ->notify(new SystemAlertNotification('critical', 'Servidor offline'));
```

## 🧪 Testando Notificações

### Enviar Teste Manual

```bash
php artisan tinker
```

```php
// Pegar um usuário de teste
$user = User::first();

// Enviar notificação de boas-vindas
$user->notify(new App\Notifications\WelcomeNotification());

// Verificar se foi para a fila
DB::table('jobs')->latest()->first();

// Processar a fila manualmente
Artisan::call('queue:work --once');
```

### Verificar Jobs na Fila

```php
// Ver total de jobs pendentes
DB::table('jobs')->count();

// Ver jobs por fila
DB::table('jobs')->where('queue', 'high')->count();
DB::table('jobs')->where('queue', 'default')->count();
DB::table('jobs')->where('queue', 'low')->count();

// Ver próximos jobs
DB::table('jobs')->orderBy('id')->limit(5)->get();
```

### Verificar Jobs Falhados

```php
// Ver jobs que falharam
DB::table('failed_jobs')->latest()->get();

// Ver último erro
$failed = DB::table('failed_jobs')->latest()->first();
echo $failed->exception;
```

## 🎯 Padrões e Boas Práticas

### Criar Nova Notificação de Email

```bash
# Gerar notificação
php artisan make:notification MinhaNotificacao
```

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MinhaNotificacao extends Notification implements ShouldQueue
{
    use Queueable;

    // Configurações recomendadas
    public $tries = 3;
    public $timeout = 60;
    public $maxExceptions = 2;

    protected $dados;

    public function __construct($dados)
    {
        $this->dados = $dados;

        // Definir prioridade da fila
        $this->onQueue('default'); // ou 'high' ou 'low'
    }

    public function via($notifiable): array
    {
        return ['mail']; // ou ['mail', 'database']
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Assunto do Email')
            ->greeting('Olá, ' . $notifiable->name)
            ->line('Sua mensagem aqui')
            ->action('Botão de Ação', url('/'))
            ->line('Obrigado!');
    }

    public function backoff(): array
    {
        // Tempo entre tentativas (segundos)
        return [30, 60, 120];
    }

    public function retryUntil(): \DateTime
    {
        // Parar de tentar após X minutos
        return now()->addMinutes(10);
    }
}
```

### Prioridades de Fila Recomendadas

| Tipo de Notificação | Fila | Justificativa |
|---------------------|------|---------------|
| Reset de senha | `high` | Segurança, usuário esperando |
| Verificação de email | `high` | Usuário esperando |
| Novo usuário registrado | `high` | Admin precisa saber |
| Alertas críticos do sistema | `high` | Ação imediata necessária |
| Boas-vindas | `default` | Importante mas não urgente |
| Alertas de warning | `default` | Informativo |
| Notificações gerais | `default` | Padrão |
| Relatórios | `low` | Pode esperar |
| Newsletters | `low` | Baixa prioridade |
| Estatísticas | `low` | Background |

### Configurações de Retry por Tipo

```php
// Alta prioridade (security, urgent)
public $tries = 5;
public function backoff(): array {
    return [15, 30, 60, 120, 240]; // Mais agressivo
}
public function retryUntil(): \DateTime {
    return now()->addMinutes(15);
}

// Prioridade normal (emails regulares)
public $tries = 3;
public function backoff(): array {
    return [30, 60, 120]; // Padrão
}
public function retryUntil(): \DateTime {
    return now()->addMinutes(10);
}

// Baixa prioridade (relatórios, newsletters)
public $tries = 2;
public function backoff(): array {
    return [60, 300]; // Mais espaçado
}
public function retryUntil(): \DateTime {
    return now()->addMinutes(20);
}
```

## 📊 Monitoramento em Produção

### Comandos Úteis para Monitoramento

```bash
# Ver status geral
./scripts/queue-status.sh

# Monitorar em tempo real
watch -n 5 './scripts/queue-status.sh'

# Ver logs dos workers
tail -f storage/logs/worker.log

# Ver apenas erros
tail -f storage/logs/worker.log | grep ERROR
```

### Criar Job de Monitoramento

```php
// Em um Command ou Job agendado
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

$failedCount = DB::table('failed_jobs')->count();

if ($failedCount > 10) {
    $admin = User::role('Admin')->first();
    $admin->notify(new SystemAlertNotification(
        'critical',
        "Muitos jobs falhados: {$failedCount}",
        ['failed_jobs' => $failedCount]
    ));
}
```

## 🚨 Troubleshooting Rápido

### Email não está sendo enviado

```bash
# 1. Verificar se worker está rodando
./scripts/queue-status.sh

# 2. Processar manualmente para ver erro
php artisan queue:work --once --verbose

# 3. Verificar configuração SMTP no .env
php artisan tinker
>>> config('mail')

# 4. Testar envio direto (sem fila)
>>> Mail::raw('Test', function($msg) { $msg->to('test@example.com')->subject('Test'); });
```

### Jobs ficam pendentes

```bash
# Verificar worker
ps aux | grep "queue:work"

# Iniciar worker se não estiver rodando
php artisan queue:work --verbose
```

### Muitos jobs falhando

```bash
# Ver detalhes dos erros
php artisan queue:failed

# Reprocessar após corrigir
php artisan queue:retry all

# Limpar falhados
php artisan queue:flush
```

## 📚 Referências

- [Documentação Principal](./QUEUE_SETUP.md)
- [Laravel Notifications](https://laravel.com/docs/12.x/notifications)
- [Laravel Mail](https://laravel.com/docs/12.x/mail)
