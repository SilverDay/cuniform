.PHONY: check test stan lint

check: lint stan test

test:
	vendor/bin/phpunit

stan:
	vendor/bin/phpstan analyse --no-progress

lint:
	vendor/bin/php-cs-fixer fix --dry-run --diff
	@php tools/escaping-lint.php templates
