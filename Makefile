.PHONY: install build test lint clean

install:
	composer install

build:
	@touch .build.stamp

test:
	vendor/bin/phpunit

lint:
	vendor/bin/php-cs-fixer fix --dry-run --diff

clean:
	rm -rf vendor/ .build.stamp .phpunit.cache .php-cs-fixer.cache
