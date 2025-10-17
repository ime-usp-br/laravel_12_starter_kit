# Sistema de Filas Assíncronas com Laravel 12

**Versão:** 0.1.3<br>
**Data:** 2025-10-17

Este projeto utiliza o sistema de filas do Laravel 12 com driver `database` para processamento assíncrono de emails e notificações.

## 📋 Índice

- [Arquitetura](#arquitetura)
- [Configuração Inicial](#configuração-inicial)
- [Uso em Desenvolvimento](#uso-em-desenvolvimento)
- [Uso em Produção](#uso-em-produção)
- [Monitoramento](#monitoramento)
- [Troubleshooting](#troubleshooting)

## 🏗️ Arquitetura

### Componentes

- **Queue Driver**: `database` (tabelas `jobs`, `failed_jobs`, `job_batches`)
- **Workers**: Processos que executam jobs da fila
- **Notifications**: Classes que implementam `ShouldQueue` para envio assíncrono

### Filas Disponíveis

- **`high`**: Prioridade alta (emails críticos, alertas)
- **`default`**: Prioridade normal (emails regulares)
- **`low`**: Prioridade baixa (relatórios, notificações não urgentes)

### Configuração

- **Timeout**: 60 segundos por job
- **Retry After**: 120 segundos (conforme Laravel 12: `timeout < retry_after`)
- **Max Tries**: 3 tentativas
- **Backoff Strategy**: Progressivo (30s, 60s, 120s)

## 🚀 Configuração Inicial

### 1. Executar Migrations

```bash
php artisan migrate
```

Isso criará as tabelas necessárias:
- `jobs` - Jobs pendentes
- `failed_jobs` - Jobs que falharam
- `job_batches` - Lotes de jobs

### 2. Verificar Configuração

Arquivo `.env`:
```env
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=120
```

## 💻 Uso em Desenvolvimento

### Com Laravel Sail (Docker)

O worker já está configurado no `docker-compose.yml`:

```bash
# Iniciar todos os serviços (incluindo queue-worker)
./vendor/bin/sail up -d

# Ver logs do worker
./vendor/bin/sail logs -f queue-worker

# Reiniciar worker
./vendor/bin/sail restart queue-worker

# Parar worker
./vendor/bin/sail stop queue-worker
```

### Com PHP Nativo

Execute o worker manualmente:

```bash
# Worker simples (Ctrl+C para parar)
php artisan queue:work database --queue=high,default,low --tries=3 --timeout=60

# Worker com recarga automática (para desenvolvimento)
php artisan queue:listen database --queue=high,default,low --tries=3 --timeout=60
```

**Diferença entre `queue:work` e `queue:listen`**:
- `queue:work`: Daemon mode, mais eficiente, requer restart para ver mudanças no código
- `queue:listen`: Recarrega automaticamente, ideal para desenvolvimento, menos eficiente

## 🏭 Uso em Produção

### Com Supervisor (Recomendado)

O Supervisor gerencia os workers automaticamente, reiniciando se travarem.

#### Instalação

```bash
# Executar script de setup (requer sudo)
sudo ./scripts/supervisor-setup.sh
```

#### Comandos do Supervisor

```bash
# Ver status
sudo supervisorctl status laravel-worker:*

# Iniciar workers
sudo supervisorctl start laravel-worker:*

# Parar workers
sudo supervisorctl stop laravel-worker:*

# Reiniciar workers (após deploy)
sudo supervisorctl restart laravel-worker:*

# Recarregar configuração
sudo supervisorctl reread
sudo supervisorctl update
```

#### Logs

```bash
# Ver logs dos workers
tail -f storage/logs/worker.log

# Ver logs do Supervisor
sudo tail -f /var/log/supervisor/supervisord.log
```

### Após Deploy

**IMPORTANTE**: Após fazer deploy com mudanças no código das filas/jobs:

```bash
# Com Supervisor
sudo supervisorctl restart laravel-worker:*

# Com Sail
./vendor/bin/sail restart queue-worker
```

## 📊 Monitoramento

### Script de Status

```bash
# Ver status completo das filas
./scripts/queue-status.sh
```

Este script mostra:
- Jobs pendentes por fila
- Jobs falhados
- Status dos workers
- Comandos úteis

### Comandos Úteis

```bash
# Ver jobs falhados
php artisan queue:failed

# Reprocessar todos os jobs falhados
php artisan queue:retry all

# Reprocessar job específico
php artisan queue:retry <job-id>

# Limpar jobs falhados
php artisan queue:flush

# Monitorar filas em tempo real
php artisan queue:monitor database:high,database:default,database:low --max=100
```

## 📧 Usando Notificações Assíncronas

### Exemplos Disponíveis

O projeto inclui três notificações de exemplo:

1. **WelcomeNotification** - Boas-vindas (fila `default`)
2. **UserRegisteredNotification** - Novo usuário (fila `high`)
3. **SystemAlertNotification** - Alertas do sistema (fila `high` ou `default`)

### Enviando Notificações

```php
use App\Notifications\WelcomeNotification;
use App\Notifications\UserRegisteredNotification;
use App\Notifications\SystemAlertNotification;

// Enviar para um usuário
$user->notify(new WelcomeNotification());

// Enviar para admin
$admin->notify(new UserRegisteredNotification());

// Alerta crítico
$admin->notify(new SystemAlertNotification(
    'critical',
    'Sistema com alto uso de memória',
    ['memory' => '95%', 'server' => 'web-01']
));

// Alerta normal
$admin->notify(new SystemAlertNotification(
    'warning',
    'Backup concluído com sucesso'
));
```

### Criando Nova Notificação Assíncrona

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

    public $tries = 3;
    public $timeout = 60;
    public $maxExceptions = 2;

    public function __construct()
    {
        // Definir fila: 'high', 'default' ou 'low'
        $this->onQueue('default');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Assunto do Email')
            ->line('Conteúdo do email...');
    }

    public function backoff(): array
    {
        // Estratégia de retry: 30s, 60s, 120s
        return [30, 60, 120];
    }

    public function retryUntil(): \DateTime
    {
        // Parar de tentar após 10 minutos
        return now()->addMinutes(10);
    }
}
```

## 🔧 Troubleshooting

### Worker não processa jobs

**Problema**: Jobs ficam na tabela `jobs` mas não são processados.

**Soluções**:
```bash
# Verificar se worker está rodando
./scripts/queue-status.sh

# Com Sail
./vendor/bin/sail ps | grep queue-worker

# Com Supervisor
sudo supervisorctl status laravel-worker:*

# Iniciar worker manualmente para debug
php artisan queue:work --verbose
```

### Jobs falhando constantemente

**Problema**: Muitos jobs na tabela `failed_jobs`.

**Soluções**:
```bash
# Ver detalhes do erro
php artisan queue:failed

# Ver job específico
php artisan tinker
>>> DB::table('failed_jobs')->latest()->first();

# Testar envio direto (sem fila)
php artisan tinker
>>> $user = User::first();
>>> Mail::to($user)->send(new WelcomeEmail());
```

**Possíveis causas**:
- Configuração SMTP incorreta (.env)
- Timeout muito baixo
- Memória insuficiente
- Erro no código da notificação

### Worker trava após alguns jobs

**Problema**: Worker para de responder.

**Soluções**:
```bash
# Limitar tempo de execução (recomendado em produção)
php artisan queue:work --max-time=3600 --memory=512

# Com Supervisor, já configurado automaticamente
sudo supervisorctl restart laravel-worker:*
```

### Jobs com timeout

**Problema**: Jobs abortados por timeout.

**Solução**: Ajustar timeout no `config/queue.php`:

```php
'database' => [
    'retry_after' => 180, // Aumentar para 3 minutos
],
```

E nas notificações:
```php
public $timeout = 120; // 2 minutos
```

**Importante**: `timeout` deve ser sempre menor que `retry_after`!

### Após deploy, mudanças não aparecem

**Problema**: Workers executam código antigo.

**Solução**: SEMPRE reiniciar workers após deploy:
```bash
# Supervisor
sudo supervisorctl restart laravel-worker:*

# Sail
./vendor/bin/sail restart queue-worker
```

## 📚 Referências

- [Laravel 12 Queues Documentation](https://laravel.com/docs/12.x/queues)
- [Laravel 12 Notifications Documentation](https://laravel.com/docs/12.x/notifications)
- [Supervisor Configuration](http://supervisord.org/configuration.html)

## 🎯 Boas Práticas

1. **Sempre use filas para emails** - Nunca envie emails síncronos em requests HTTP
2. **Configure retry inteligente** - Use `backoff()` e `retryUntil()`
3. **Monitore jobs falhados** - Configure alertas para `failed_jobs`
4. **Limite tempo de execução** - Use `--max-time` em produção
5. **Reinicie workers após deploy** - Workers em daemon não veem mudanças no código
6. **Use filas com prioridade** - Separe jobs críticos (`high`) de normais (`default`)
7. **Teste localmente primeiro** - Use `queue:listen` em desenvolvimento
8. **Configure Supervisor em produção** - Nunca use `queue:work` direto em produção
9. **Monitore uso de memória** - Workers podem acumular memória ao longo do tempo
10. **Documente notificações customizadas** - Facilita manutenção futura
