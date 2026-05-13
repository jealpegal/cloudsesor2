#!/bin/bash
# Crear base de datos e importar schema para CloudSensor
# Uso: ./setup-db.sh
# Te pedirá la contraseña de MySQL (usuario root)

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "=== Creando base de datos cloudsensor ==="
mysql -u jealpegal -p -e "CREATE DATABASE IF NOT EXISTS cloudsensor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo ""
echo "=== Importando tablas (schema.sql) ==="
mysql -u jealpegal -p cloudsensor < "$SCRIPT_DIR/schema.sql"

echo ""
echo "=== Listo. Base de datos 'cloudsensor' creada e importada."
echo "Para arrancar el backend: cd $PROJECT_DIR/backend && php -S localhost:8000 router.php"
