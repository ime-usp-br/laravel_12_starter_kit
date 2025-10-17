# Production Deployment Guide

This guide covers deploying the Laravel 12 Starter Kit to production using Docker.

## Prerequisites

- Docker Engine 20.10+
- Docker Compose v2 (plugin)
- Sufficient disk space (minimum 5GB recommended)
- Root or sudo access

## Quick Start

```bash
# 1. Clone the repository
git clone <your-repo-url>
cd laravel_12_starter_kit

# 2. Copy and configure environment
cp .env.example .env
nano .env  # Configure production settings

# 3. Run deployment script
sudo ./scripts/deploy.sh
```

## Architecture

The production setup uses:

- **Nginx + PHP-FPM**: Combined in a single container for simplicity
- **MySQL 8.0**: Database with persistent storage
- **Queue Worker**: Separate container for background job processing
- **Multi-stage builds**: Optimized image size
- **Health checks**: Automated container health monitoring

## Environment Configuration

### Required Variables

Edit `.env` and configure these essential variables:

```bash
# Application
APP_NAME=YourAppName
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_KEY=  # Will be auto-generated on first deploy

# Database
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_user
DB_PASSWORD=SECURE_PASSWORD_HERE
DB_ROOT_PASSWORD=SECURE_ROOT_PASSWORD_HERE

# Docker Configuration
APP_PORT=80
APP_VERSION=1.0.0
WWWUSER=1000
WWWGROUP=1000

# Network (optional, defaults provided)
DOCKER_NETWORK_SUBNET=172.20.0.0/16
DOCKER_NETWORK_GATEWAY=172.20.0.1
```

### Optional: External Database (e.g., USP Replicado)

If you need to connect to external databases like USP's Replicado system:

```bash
REPLICADO_HOST=replicado.server.com
REPLICADO_PORT=1433
REPLICADO_DATABASE=replicado
REPLICADO_USERNAME=your_username
REPLICADO_PASSWORD=your_password
REPLICADO_CODUNDCLG=45
```

## Deployment Script

The `scripts/deploy.sh` script automates the entire deployment process:

### What it does:

1. **Prerequisites Check**: Verifies Docker, Docker Compose, and `.env` file
2. **Database Backup**: Creates backup before deployment (if existing deployment)
3. **Build Images**: Multi-stage Docker build for optimized production images
4. **Deploy**: Zero-downtime deployment with health checks
5. **Verification**: Tests application health and endpoints
6. **Cleanup**: Removes old Docker images (keeps last 3 versions)

### Usage:

```bash
# Full deployment
sudo ./scripts/deploy.sh
```

## Manual Deployment

If you prefer manual control:

```bash
# Build images
docker build -f docker/production/Dockerfile -t your-app:latest .

# Start services
docker compose -f docker-compose.prod.yml up -d

# Check status
docker compose -f docker-compose.prod.yml ps

# View logs
docker compose -f docker-compose.prod.yml logs -f app
```

## Common Operations

### View Logs

```bash
# All services
docker compose -f docker-compose.prod.yml logs -f

# Specific service
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml logs -f worker
docker compose -f docker-compose.prod.yml logs -f mysql
```

### Execute Commands Inside Container

```bash
# Run artisan commands
docker compose -f docker-compose.prod.yml exec app php artisan migrate

# Access shell
docker compose -f docker-compose.prod.yml exec app bash

# Run composer
docker compose -f docker-compose.prod.yml exec app composer install
```

### Restart Services

```bash
# Restart all
docker compose -f docker-compose.prod.yml restart

# Restart specific service
docker compose -f docker-compose.prod.yml restart app
docker compose -f docker-compose.prod.yml restart worker
```

### Stop Services

```bash
# Stop without removing containers
docker compose -f docker-compose.prod.yml stop

# Stop and remove containers
docker compose -f docker-compose.prod.yml down

# Stop and remove everything (including volumes)
docker compose -f docker-compose.prod.yml down -v
```

## Database Management

### Backup Database

```bash
# Create backup
docker compose -f docker-compose.prod.yml exec mysql \
  mysqldump -u root -p"${DB_ROOT_PASSWORD}" ${DB_DATABASE} > backup.sql

# Restore backup
docker compose -f docker-compose.prod.yml exec -T mysql \
  mysql -u root -p"${DB_ROOT_PASSWORD}" ${DB_DATABASE} < backup.sql
```

### Run Migrations

```bash
docker compose -f docker-compose.prod.yml exec app php artisan migrate
```

## Troubleshooting

### Application won't start

```bash
# Check logs for errors
docker compose -f docker-compose.prod.yml logs app

# Verify environment variables
docker compose -f docker-compose.prod.yml config

# Check container status
docker compose -f docker-compose.prod.yml ps
```

### Permission Issues

```bash
# Fix storage permissions
docker compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
docker compose -f docker-compose.prod.yml exec app chmod -R 775 storage bootstrap/cache
```

### Database Connection Issues

```bash
# Check MySQL is running
docker compose -f docker-compose.prod.yml ps mysql

# Test connection from app container
docker compose -f docker-compose.prod.yml exec app php artisan tinker
>>> DB::connection()->getPdo();
```

### Reset Everything

```bash
# WARNING: This will delete all data!
docker compose -f docker-compose.prod.yml down -v
docker system prune -a
sudo rm -rf storage/logs/*
```

## Performance Tuning

### PHP-FPM Configuration

Edit `docker/production/php-fpm.conf`:

```ini
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
```

### Nginx Configuration

Edit `docker/production/nginx.conf` for custom Nginx settings.

### Database Optimization

Edit `docker-compose.prod.yml` MySQL command section for custom MySQL settings.

## Security Checklist

- [ ] Change all default passwords in `.env`
- [ ] Set `APP_DEBUG=false` in production
- [ ] Generate secure `APP_KEY`
- [ ] Use strong database passwords (16+ characters)
- [ ] Enable HTTPS/SSL (configure reverse proxy)
- [ ] Restrict MySQL port exposure (remove FORWARD_DB_PORT if not needed)
- [ ] Keep Docker images updated
- [ ] Regular security audits
- [ ] Monitor logs for suspicious activity
- [ ] Set up automated backups

## SSL/HTTPS Configuration

For production, use a reverse proxy (Nginx, Traefik, Caddy) in front of the application:

### Example with Let's Encrypt (Certbot)

```bash
# Install certbot
sudo apt install certbot python3-certbot-nginx

# Get certificate
sudo certbot --nginx -d yourdomain.com

# Auto-renewal (add to crontab)
0 3 * * * certbot renew --quiet
```

## Monitoring

### Health Check Endpoint

The application includes a health check endpoint at `/health` (if configured).

### Container Health

```bash
# Check health status
docker inspect --format='{{.State.Health.Status}}' your-app-app
```

### Resource Usage

```bash
# View resource usage
docker stats

# View disk usage
docker system df
```

## Backup Strategy

### Automated Backups

Create a cron job for automated backups:

```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * /path/to/project/scripts/backup.sh
```

### Backup Locations

- Database backups: `/backups/pre-deploy/`
- Application backups: Customize in backup script

## Updating

### Update Application Code

```bash
# Pull latest changes
git pull origin main

# Rebuild and deploy
sudo ./scripts/deploy.sh
```

### Update Dependencies

```bash
# Update composer dependencies
docker compose -f docker-compose.prod.yml exec app composer update

# Update npm dependencies
docker compose -f docker-compose.prod.yml exec app npm update

# Rebuild assets
docker compose -f docker-compose.prod.yml exec app npm run build
```

## Additional Resources

- [Laravel Deployment Documentation](https://laravel.com/docs/deployment)
- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)

## Support

For issues specific to this deployment setup, check:
- Container logs: `docker compose -f docker-compose.prod.yml logs`
- Laravel logs: `storage/logs/laravel.log`
- Nginx logs: Available in container logs
