# 📬 Sistema de Filas Assíncronas - Laravel 12

Sistema completo de filas assíncronas implementado com **database driver** e workers gerenciados via **Supervisor** (PHP nativo) ou **Docker Sail**.

## ✅ Implementado

### Configuração
- ✅ Queue driver `database` configurado
- ✅ Migrations para `jobs`, `failed_jobs`, `job_batches`
- ✅ Timeout ajustado: 60s (< 120s retry_after conforme Laravel 12)
- ✅ 3 filas com prioridades: `high`, `default`, `low`

### Notificações Assíncronas
- ✅ **ResetPasswordNotification** - Reset de senha (substitui Breeze)
- ✅ **WelcomeNotification** - Boas-vindas para novos usuários
- ✅ **UserRegisteredNotification** - Notifica admins sobre novos cadastros
- ✅ **SystemAlertNotification** - Alertas do sistema (critical/warning/info)

Todas implementam `ShouldQueue` com:
- Retry inteligente com backoff progressivo
- Timeout configurado
- Max exceptions handling
- Priorização por fila

### Workers

#### PHP Nativo (Supervisor)
- ✅ Arquivo de configuração: `config/supervisor/laravel-worker.conf`
- ✅ Script de instalação: `scripts/supervisor-setup.sh`
- ✅ 2 workers rodando em paralelo
- ✅ Auto-restart em caso de falha
- ✅ Logs em `storage/logs/worker.log`

#### Docker Sail
- ✅ Serviço `queue-worker` no `docker-compose.yml`
- ✅ Auto-start com `sail up`
- ✅ Restart policy: `unless-stopped`
- ✅ Mesma configuração de filas e timeouts

### Scripts e Monitoramento
- ✅ `scripts/queue-status.sh` - Monitor completo de filas
- ✅ `scripts/supervisor-setup.sh` - Instalação automática do Supervisor
- ✅ Paths dinâmicos (não hardcoded, versionável)

### Documentação
- ✅ `docs/QUEUE_SETUP.md` - Guia completo de setup e troubleshooting
- ✅ `docs/QUEUE_EXAMPLES.md` - Exemplos práticos de uso

## 🚀 Quick Start

### Sail (Docker)

```bash
# Iniciar serviços incluindo queue-worker
./vendor/bin/sail up -d

# Ver status
./scripts/queue-status.sh

# Ver logs do worker
./vendor/bin/sail logs -f queue-worker
```

### PHP Nativo

```bash
# Instalar Supervisor (uma vez)
sudo ./scripts/supervisor-setup.sh

# Ver status
sudo supervisorctl status laravel-worker:*

# Monitorar
./scripts/queue-status.sh
```

## 📧 Usar Notificações

```php
use App\Notifications\WelcomeNotification;

// Email de boas-vindas (assíncrono)
$user->notify(new WelcomeNotification());

// Reset de senha (já integrado automaticamente)
// O User model já usa a notificação assíncrona
```

## 📚 Documentação Completa

- **[QUEUE_SETUP.md](docs/QUEUE_SETUP.md)** - Setup, configuração e troubleshooting
- **[QUEUE_EXAMPLES.md](docs/QUEUE_EXAMPLES.md)** - Exemplos práticos e boas práticas

## 🎯 Características Principais

### Conforme Laravel 12
- ✅ `timeout (60s) < retry_after (120s)` - Requirement da doc oficial
- ✅ ShouldQueue + Queueable trait
- ✅ backoff() e retryUntil() methods
- ✅ Database driver com table management

### Pronto para Produção
- ✅ Supervisor gerenciando workers
- ✅ Auto-restart em falhas
- ✅ Logs persistentes
- ✅ Múltiplas filas priorizadas
- ✅ Retry strategy inteligente

### Developer Friendly
- ✅ Scripts de setup automatizados
- ✅ Monitoring integrado
- ✅ Sail compatibility out-of-the-box
- ✅ Paths dinâmicos (versionável)
- ✅ Documentação extensa

## 📊 Arquitetura

```
┌─────────────────┐
│   Application   │
│  (Controllers)  │
└────────┬────────┘
         │ notify()
         ▼
┌─────────────────┐
│  Notifications  │  ShouldQueue
│  (Queueable)    │
└────────┬────────┘
         │ dispatch
         ▼
┌─────────────────┐
│   Jobs Table    │  database driver
│  (MySQL/Sail)   │  3 queues: high|default|low
└────────┬────────┘
         │
    ┌────┴──────────────────┐
    ▼                       ▼
┌──────────┐          ┌──────────┐
│ Worker 1 │          │ Worker 2 │  Supervisor/Docker
│  PHP     │          │  PHP     │  Auto-restart
└────┬─────┘          └────┬─────┘  Timeout: 60s
     │                     │        Retry: 3x
     ▼                     ▼
┌─────────────────────────────┐
│      SMTP Server            │
│   (Gmail/Mailgun/etc)       │
└─────────────────────────────┘
```

## ⚠️ Importante

### Após Deploy
Sempre reiniciar workers para carregar novo código:

```bash
# Supervisor
sudo supervisorctl restart laravel-worker:*

# Sail
./vendor/bin/sail restart queue-worker
```

### Monitorar Jobs Falhados
```bash
# Ver falhados
php artisan queue:failed

# Reprocessar
php artisan queue:retry all
```

## 🛠️ Comandos Rápidos

```bash
# Status completo
./scripts/queue-status.sh

# Processar 1 job (debug)
php artisan queue:work --once --verbose

# Limpar falhados
php artisan queue:flush

# Monitorar em tempo real
php artisan queue:monitor database:high,database:default,database:low
```

---

**Desenvolvido seguindo boas práticas do Laravel 12 e pronto para produção! 🚀**
