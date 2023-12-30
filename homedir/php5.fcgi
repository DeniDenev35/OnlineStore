#!/bin/sh

export DEFAULTPHPINI=/usr/local/lib/php-fcgi.ini

exec /usr/bin/php -c ${DEFAULTPHPINI}

