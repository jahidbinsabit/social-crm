#!/bin/bash
set -e

echo "=== Enabling social-crm Apache vhost ==="
sudo a2ensite social-crm.conf
sudo a2dissite 000-default.conf

echo "=== Enabling required modules ==="
sudo a2enmod rewrite headers

echo "=== Restarting Apache ==="
sudo systemctl restart apache2

echo "=== Testing Laravel response ==="
sleep 1
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://127.0.0.1/login || true

echo "=== Done ==="
