#!/usr/bin/env bash
#MISE description="Run all quality tools"
#USAGE flag "--check-violations" help="Only check for violations, do not fix them"

set -e

CHECK_VIOLATIONS="${usage_check_violations:-false}"

echo
echo "Running PHP CS Fixer..."
if [ "${CHECK_VIOLATIONS}" == "true" ]
then
    mise run in-app-container php bin/php-cs-fixer.php check
else
    mise run in-app-container php bin/php-cs-fixer.php fix
fi

echo
echo "Running Prettier..."

if [ "${CHECK_VIOLATIONS}" == "true" ]
then
    mise run npm run prettier
else
    mise run npm run prettier:fix
fi

echo
echo "Running PHPStan..."
mise run in-app-container php vendor/bin/phpstan --memory-limit=1024M

echo
echo "All checks and cleanups completed successfully! ✨"
