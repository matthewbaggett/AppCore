SHELL := /bin/bash

ifndef CI_PROJECT_NAME
CI_PROJECT_NAME:=$(shell basename $(PWD))
endif

ifndef CI_COMMIT_SHORT_SHA
CI_COMMIT_SHORT_SHA := $(shell git rev-parse --short HEAD)
endif

all: clean setup test clean

define setup
	composer install && \
	docker-compose pull && \
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		up -d redis test \
		&& \
	sleep 10
endef

test:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		exec test vendor/phpunit/phpunit/phpunit

clean:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		down -v

dev-test:
	@[[ -z `docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) ps -q test` ]] \
		&& (echo "Starting Services" && $(call setup)) \
		|| echo "Services already running"
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		exec test vendor/phpunit/phpunit/phpunit \
			--stop-on-failure --stop-on-error \
			--no-coverage

dev-logs:
	docker-compose -p $(CI_PROJECT_NAME)_$(CI_COMMIT_SHORT_SHA) \
		logs -f