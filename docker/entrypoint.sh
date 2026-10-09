#!/bin/sh
set -eu
cd /var/www/html

# Database migrations are deliberately never run when a container starts.
case "${1:-web}" in
    web) exec docker-php-entrypoint apache2-foreground ;;
    worker) exec php artisan queue:work --sleep=1 --tries=3 --timeout=60 --memory=256 ;;
    scheduler) exec php artisan schedule:work ;;
    reverb) exec php artisan reverb:start --host=0.0.0.0 --port=8080 ;;
    *) exec docker-php-entrypoint "$@" ;;
esac
