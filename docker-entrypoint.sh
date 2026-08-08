#!/bin/bash

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

#čisti stari keš konfiguracije da Laravel sigurno povuče nov .env
echo "Čistim keš konfiguracije..."
php artisan config:clear
php artisan cache:clear

#Ovo sprečava da Laravel pokuša migraciju pre nego što MySQL uopšte ustane
echo "Pokrećem migracije baze podataka (ako ima novih)..."
php artisan migrate --force
php artisan db:seed --force

#Pokreni zvaničnu Apache komandu (mora biti zadnja linija)
echo "Startujem Apache server..."
exec apache2-foreground