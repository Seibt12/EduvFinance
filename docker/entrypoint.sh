#!/bin/bash
set -e

echo ""
echo "╔══════════════════════════════════════════╗"
echo "║       EduFinance — Inicializando         ║"
echo "╚══════════════════════════════════════════╝"
echo ""

# ── 1. Aguarda o PostgreSQL ficar acessível na rede ─────────
echo "⏳ Aguardando PostgreSQL em ${DB_HOST}:${DB_PORT}..."

RETRIES=30
until (echo > /dev/tcp/"${DB_HOST}"/"${DB_PORT}") 2>/dev/null; do
    RETRIES=$((RETRIES - 1))
    if [ "$RETRIES" -le 0 ]; then
        echo "❌ Timeout: PostgreSQL não respondeu a tempo."
        exit 1
    fi
    sleep 1
done

# Pequena espera extra para o pg_isready confirmar prontidão total
sleep 2

echo "✅ PostgreSQL pronto!"
echo ""

# ── 2. Cria o admin via PHP (usa password_hash correto) ─────
echo "🔧 Verificando usuário administrador..."
php /var/www/html/backend/setup.php
echo ""

# ── 3. Sobe o Apache em foreground ──────────────────────────
echo "🚀 Iniciando Apache..."
echo ""
echo "  Acesse o sistema:  http://localhost:8080"
echo "  DBeaver:           localhost:${DB_PORT} / ${DB_NAME}"
echo "  Admin:             admin@email.com / 123"
echo ""

exec apache2-foreground
