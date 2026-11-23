#!/usr/bin/env bash
set -euo pipefail
APP=/workspaces/AutoServe/AutoServe

echo "Waiting for MySQL..."
for i in $(seq 1 60); do
  mysqladmin ping -h 127.0.0.1 -uroot --silent && break
  sleep 2
done

mysql -h 127.0.0.1 -uroot < "$APP/database/schema.sql"
mysql -h 127.0.0.1 -uroot < "$APP/database/sample_data.sql"
php "$APP/setup/seed.php" | sed 's/<br>/\n/g'
echo "Database ready."
