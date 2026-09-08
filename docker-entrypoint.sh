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

#Seed se više NE pokreće automatski na svaki deploy - baza sad ima prave podatke,
#a seederi (npr. UserSeeder) prave test korisnike sa fiksnim email-ovima bez provere
#duplikata, pa bi pucali na unique constraint pri svakom restartu kontejnera.
#Ako ikad zatreba ponovni seed (npr. na potpuno novoj/praznoj bazi), pokreni ga ručno:
#Render dashboard -> Shell tab -> php artisan db:seed --force

#Pokreni zvaničnu Apache komandu (mora biti zadnja linija)
echo "Startujem Apache server..."
exec apache2-foreground