all: clean setup test clean

setup:
	composer install
	docker-compose pull
	#docker-compose build --pull test
	docker-compose up -d redis
	sleep 5

test:
	docker-compose run test vendor/bin/phpunit \
		--stop-on-failure --stop-on-error \
		--no-coverage

clean:
	docker-compose down -v

dev-test:
	docker-compose run test vendor/bin/phpunit \
		--stop-on-failure --stop-on-error \
		--no-coverage