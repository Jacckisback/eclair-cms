#!/usr/bin/env bash
# SPDX-License-Identifier: AGPL-3.0-only
# Copyright (c) 2026 Thierry Gustin
#
# Vérifie l'archive construite sur de VRAIS serveurs web (Apache + mod_php avec le .htaccess livré, Apache avec le
# .htaccess d'une 1.x gardé, Nginx + PHP-FPM avec les règles de docs/GUIDE.md et docs/UPGRADE-2.md), avec
# tests/servers.php. Pour la CI (Ubuntu, droits root) :
#
#   sudo tools/ci-servers.sh dist/eclair-cms-2.0.0.zip
#
# Prérequis : apache2 libapache2-mod-php nginx php-fpm (et les extensions PHP d'Éclair) installés.
set -euo pipefail
zip=$(realpath "$1")
repo=$(cd "$(dirname "$0")/.." && pwd)
base=/srv/eclair-ci
fpm=$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)
fresh() { rm -rf "$base/$1" && mkdir -p "$base/$1" && unzip -q "$zip" -d "$base/$1" && chown -R www-data:www-data "$base/$1"; }

a2enmod rewrite headers expires >/dev/null
sed -i 's/^Listen 80$/#Listen 80/' /etc/apache2/ports.conf
cat > /etc/apache2/sites-available/eclair-ci.conf <<CONF
Listen 8081
<VirtualHost *:8081>
    ServerName localhost
    DocumentRoot $base/apache/eclair
    <Directory $base/apache/eclair>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
CONF
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite eclair-ci >/dev/null

echo "▶▶ Apache, .htaccess livré"
fresh apache
apache2ctl restart || apache2ctl start
sleep 1
php "$repo/tests/servers.php" http://127.0.0.1:8081 "$base/apache/eclair"

echo "▶▶ Apache, .htaccess d'une 1.x gardé (sans la règle site/)"
fresh apache
unzip -p "$repo/dist/eclair-cms-1.14.1.zip" eclair/.htaccess > "$base/apache/eclair/.htaccess"
chown www-data:www-data "$base/apache/eclair/.htaccess"
php "$repo/tests/servers.php" http://127.0.0.1:8081 "$base/apache/eclair"
apache2ctl stop || true

echo "▶▶ Nginx + PHP-FPM, règles de la documentation"
[ -n "$fpm" ] || { service "$(ls /etc/init.d | grep -m1 php.*fpm)" start; sleep 1; fpm=$(ls /run/php/php*-fpm.sock | head -1); }
fresh nginx
cat > /etc/nginx/sites-enabled/eclair-ci <<CONF
server {
    listen 8082;
    root $base/nginx/eclair;
    location ~ ^/site/(themes|extensions)(/|\$)    { return 404; }
    location ~ ^/site/assets/.*\.(php\d?|phtml|phar|inc|html?)\$ { return 404; }
    location ~ ^/(app|data|cache)(/|\$) { return 404; }
    location ~ /\.(?!well-known/) { return 404; }
    location ^~ /media/ {
        location ~* \.(php\d?|phtml|phar|inc|pl|py|cgi|sh|asp|aspx|jsp|s?html?|xht(ml)?|svgz?|xml|m?js)\$ { return 404; }
        add_header X-Content-Type-Options nosniff;
        expires 1y;
    }
    location ~* \.(webp|avif|jpe?g|png|gif|ico|css|js|woff2)\$ { expires 1y; add_header Cache-Control "public, immutable"; }
    location / { try_files \$uri /index.php\$is_args\$args; }
    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root/index.php;
        fastcgi_pass unix:$fpm;
    }
    location ~ \.php\$ { return 404; }
}
CONF
rm -f /etc/nginx/sites-enabled/default
nginx -t
(nginx -s reload 2>/dev/null || nginx)
sleep 1
php "$repo/tests/servers.php" http://127.0.0.1:8082 "$base/nginx/eclair"
