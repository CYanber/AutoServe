#!/usr/bin/env bash
APP=/workspaces/AutoServe/AutoServe
pkill -f "php -S 0.0.0.0:8000" 2>/dev/null || true
nohup php -S 0.0.0.0:8000 -t "$APP" > /tmp/php-server.log 2>&1 &
echo "AutoServe running on port 8000"
