#!/bin/bash

# Laravel Queue Status Monitor Script
# Monitora o status das filas e workers

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

# Get project path dynamically
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_PATH="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_PATH"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Laravel Queue Status Monitor${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check if running in Sail or native PHP
if [ -f "./vendor/bin/sail" ] && docker ps | grep -q "laravel.test"; then
    ARTISAN="./vendor/bin/sail artisan"
    IS_SAIL=true
    echo -e "${GREEN}🐳 Ambiente: Docker Sail${NC}"
else
    ARTISAN="php artisan"
    IS_SAIL=false
    echo -e "${GREEN}💻 Ambiente: PHP Nativo${NC}"
fi

echo ""
echo -e "${YELLOW}📊 Jobs Pendentes por Fila:${NC}"
echo "-----------------------------------"
$ARTISAN queue:monitor database:high,database:default,database:low --max=100 2>/dev/null || echo "Nenhum job pendente"

echo ""
echo -e "${YELLOW}❌ Jobs Falhados:${NC}"
echo "-----------------------------------"
FAILED_COUNT=$($ARTISAN queue:failed 2>/dev/null | grep -c "| " || echo "0")
if [ "$FAILED_COUNT" -gt 1 ]; then
    echo -e "${RED}Total: $((FAILED_COUNT - 1)) jobs falhados${NC}"
    $ARTISAN queue:failed | head -20
else
    echo -e "${GREEN}Nenhum job falhado${NC}"
fi

echo ""
echo -e "${YELLOW}👷 Status dos Workers:${NC}"
echo "-----------------------------------"
if [ "$IS_SAIL" = true ]; then
    # Check Sail worker
    if docker ps --format "table {{.Names}}\t{{.Status}}" | grep -q "queue-worker"; then
        echo -e "${GREEN}✅ Queue Worker (Sail): Running${NC}"
        docker ps --format "table {{.Names}}\t{{.Status}}" | grep "queue-worker"
    else
        echo -e "${RED}❌ Queue Worker (Sail): Not Running${NC}"
        echo -e "${YELLOW}   Execute: ./vendor/bin/sail up -d queue-worker${NC}"
    fi
else
    # Check Supervisor workers
    if command -v supervisorctl &> /dev/null; then
        supervisorctl status laravel-worker:* 2>/dev/null || echo -e "${RED}❌ Supervisor não configurado${NC}\n   Execute: sudo ./scripts/supervisor-setup.sh"
    else
        echo -e "${RED}❌ Supervisor não está instalado${NC}"
        echo -e "${YELLOW}   Execute: sudo apt-get install supervisor${NC}"
    fi
fi

echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  Comandos Úteis${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
if [ "$IS_SAIL" = true ]; then
    echo -e "Ver logs do worker:    ${YELLOW}sail logs -f queue-worker${NC}"
    echo -e "Reiniciar worker:      ${YELLOW}sail restart queue-worker${NC}"
    echo -e "Reprocessar falhados:  ${YELLOW}sail artisan queue:retry all${NC}"
    echo -e "Limpar falhados:       ${YELLOW}sail artisan queue:flush${NC}"
else
    echo -e "Ver logs do worker:    ${YELLOW}tail -f storage/logs/worker.log${NC}"
    echo -e "Reiniciar workers:     ${YELLOW}sudo supervisorctl restart laravel-worker:*${NC}"
    echo -e "Reprocessar falhados:  ${YELLOW}php artisan queue:retry all${NC}"
    echo -e "Limpar falhados:       ${YELLOW}php artisan queue:flush${NC}"
fi
echo ""
