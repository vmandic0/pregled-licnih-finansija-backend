#!/bin/bash
set -Eeuo pipefail

#Pozicioniraj se u folder aplikacije na samom početku
cd /var/www/html/PregledLicnihFinansija

#Ako .env ne postoji u Laravel folderu, kopiraj ga iz .env.example
if [ ! -f .env ]; then
    echo "Kreiram .env fajl..."
    cp .env.example .env
fi

#Generiši Laravel ključ ako već nije postavljen
if ! grep -q "APP_KEY=base64" .env || grep -q "APP_KEY=$" .env; then
    echo "Generišem Laravel APP_KEY..."
    php artisan key:generate --force
fi

# Očisti keš konfiguracije da Laravel sigurno povuče nov .env.
echo "Čistim keš konfiguracije..."
php artisan config:clear

#Kreira/ažurira tabele u bazi
echo "Pokrećem migracije baze podataka (ako ima novih)..."
php artisan migrate --force --no-interaction
echo "Status migracija:"
php artisan migrate:status --no-interaction

# Database cache ne može da se očisti pre nego što se kreira cache tabela.
echo "Čistim aplikacioni keš..."
php artisan cache:clear

#Seed je idempotentan (firstOrCreate), pa je bezbedno da ide na svakom startu.
#Mora ovde jer Render free plan nema Shell.
echo "Pokrećem seed (kategorije, admin, demo nalog)..."
php artisan db:seed --force

#Pokreni zvaničnu Apache komandu (mora biti zadnja linija)
echo "Startujem Apache server..."
exec apache2-foreground