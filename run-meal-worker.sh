#!/bin/bash
cd /home/u909637026/websites/HtkVIqK5q/public_html/dietsdna || exit 1
exec >> storage/logs/meal-worker-cron.log 2>&1
echo "Cron started: $(date -Is)"
/usr/bin/flock -n storage/framework/meal-plans-worker.lock /usr/bin/php artisan queue:work database --queue=meal-plans --once --timeout=300 --tries=1 -v
worker_result=$?
echo "Worker exit code: $worker_result"
exit "$worker_result"
