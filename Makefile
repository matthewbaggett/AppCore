ifndef CI_PROJECT_NAME
CI_PROJECT_NAME:=Facetizer
endif

ifndef CI_COMMIT_SHORT_SHA
CI_COMMIT_SHORT_SHA:=$(shell git rev-parse --short HEAD)
endif

all: clean setup test clean

setup:
	composer install
	docker-compose pull
	#docker-compose build --pull test
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		up -d redis
	sleep 10

test:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		run test vendor/bin/phpunit \
		--stop-on-failure --stop-on-error \
		--no-coverage

clean:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		down -v

dev-test:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		run test vendor/phpunit/phpunit/phpunit \
		--stop-on-failure --stop-on-error \
		--no-coverage
