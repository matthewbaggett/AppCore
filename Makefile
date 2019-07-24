SHELL := /bin/bash

ifndef CI_PROJECT_NAME
CI_PROJECT_NAME:=$(shell basename $(PWD))
endif

ifndef CI_COMMIT_SHORT_SHA
CI_COMMIT_SHORT_SHA := $(shell git rev-parse --short HEAD)
endif

dev-test: setup phpunit

all: clean setup test clean

define setup
	composer install && \
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) pull && \
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) build --pull test && \
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		up -d redis test \
		&& \
	sleep 10
endef

setup:
	@[[ -z `docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) ps -q test` ]] \
		&& (echo "Starting Services" && $(call setup)) \
		|| echo "Services already running"

test:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		exec -T \
			test vendor/phpunit/phpunit/phpunit

clean:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		down -v

phpunit:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		exec -T \
			test vendor/phpunit/phpunit/phpunit \
				--stop-on-failure --stop-on-error \
				--no-coverage

dev-logs:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		logs -f

bash:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		exec test bash