#!/bin/bash
# Queue Worker Script
# Runs the PHP queue worker with automatic restart on failure

set -e

echo "🔄 Starting PHPFrarm Queue Worker..."

cd /var/www/html

# Wait for Redis to be ready using PHP
echo "⏳ Waiting for Redis..."
until php -r "try { \$r = new Redis(); \$r->connect('${REDIS_HOST:-redis}', ${REDIS_PORT:-6379}, 2); echo 'ok'; } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
  sleep 2
done
echo "✅ Redis is ready"

# Wait for MySQL to be ready using PHP
echo "⏳ Waiting for MySQL..."
until php -r "try { new PDO('mysql:host=${MYSQL_HOST:-mysql};port=${MYSQL_PORT:-3306}', '${MYSQL_USER:-root}', '${MYSQL_PASSWORD:-}', [PDO::ATTR_TIMEOUT => 2]); echo 'ok'; } catch (Exception \$e) { exit(1); }" 2>/dev/null; do
  sleep 2
done
echo "✅ MySQL is ready"

# Run worker with auto-restart
while true; do
    echo "🔧 Starting queue worker process..."
    
    # Run worker with max 1000 jobs or 3600 seconds (1 hour)
    # Then restart to prevent memory leaks
    php artisan queue:work --max-jobs=1000 --max-time=3600 --sleep=3 || {
        echo "⚠️  Worker exited with error, restarting in 5 seconds..."
        sleep 5
    }
    
    echo "♻️  Worker completed cycle, restarting..."
    sleep 1
done
