.PHONY: install build test test-e2e lint clean

install:
	composer install

build:
	@touch .build.stamp

test:
	vendor/bin/phpunit --testsuite default

test-e2e:
	vendor/bin/phpunit --testsuite e2e

lint:
	vendor/bin/php-cs-fixer fix --dry-run --diff

clean:
	rm -rf vendor/ .build.stamp .phpunit.cache .php-cs-fixer.cache
