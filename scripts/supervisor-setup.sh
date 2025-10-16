#!/bin/bash

# Laravel Queue Worker - Supervisor Setup Script
# Este script configura o Supervisor para gerenciar os workers do Laravel

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get project path dynamically (script is in PROJECT_ROOT/scripts/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_PATH="$(dirname "$SCRIPT_DIR")"
PROJECT_USER="${SUDO_USER:-$USER}"
SUPERVISOR_CONF="/etc/supervisor/conf.d/laravel-worker.conf"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Laravel Queue Worker - Supervisor Setup${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Este script precisa ser executado como root${NC}"
    echo -e "${YELLOW}Use: sudo ./scripts/supervisor-setup.sh${NC}"
    exit 1
fi

# Check if supervisor is installed
if ! command -v supervisorctl &> /dev/null; then
    echo -e "${YELLOW}⚠️  Supervisor não está instalado. Instalando...${NC}"
    apt-get update
    apt-get install -y supervisor
fi

# Copy configuration file and inject environment variables
echo -e "${GREEN}📋 Configurando Supervisor com paths dinâmicos...${NC}"
echo -e "${YELLOW}   Project Path: $PROJECT_PATH${NC}"
echo -e "${YELLOW}   Project User: $PROJECT_USER${NC}"

# Process template and create final config
sed -e "s|%(ENV_PROJECT_PATH)s|$PROJECT_PATH|g" \
    -e "s|%(ENV_PROJECT_USER)s|$PROJECT_USER|g" \
    "$PROJECT_PATH/config/supervisor/laravel-worker.conf" > "$SUPERVISOR_CONF"

# Reload supervisor configuration
echo -e "${GREEN}🔄 Recarregando configuração do Supervisor...${NC}"
supervisorctl reread
supervisorctl update

# Start the workers
echo -e "${GREEN}▶️  Iniciando workers...${NC}"
supervisorctl start laravel-worker:*

# Check status
echo ""
echo -e "${GREEN}✅ Setup concluído!${NC}"
echo ""
echo -e "${YELLOW}Status dos workers:${NC}"
supervisorctl status laravel-worker:*

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Comandos úteis:${NC}"
echo -e "${GREEN}========================================${NC}"
echo -e "Ver status:           ${YELLOW}sudo supervisorctl status${NC}"
echo -e "Parar workers:        ${YELLOW}sudo supervisorctl stop laravel-worker:*${NC}"
echo -e "Iniciar workers:      ${YELLOW}sudo supervisorctl start laravel-worker:*${NC}"
echo -e "Reiniciar workers:    ${YELLOW}sudo supervisorctl restart laravel-worker:*${NC}"
echo -e "Ver logs:             ${YELLOW}tail -f $PROJECT_PATH/storage/logs/worker.log${NC}"
echo -e "Recarregar config:    ${YELLOW}sudo supervisorctl reread && sudo supervisorctl update${NC}"
echo ""
