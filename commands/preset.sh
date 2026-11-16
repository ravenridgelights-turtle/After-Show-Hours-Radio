#!/bin/sh
exec /usr/bin/php "${0%/*}/radio-command.php" preset "$@"
