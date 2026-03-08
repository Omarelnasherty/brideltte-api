#!/bin/bash
PORT="${PORT:-80}"
exec php -S 0.0.0.0:${PORT} /var/www/html/router.php
