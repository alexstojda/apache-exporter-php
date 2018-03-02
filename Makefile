all: build
.PHONY: all

build:
	docker-compose build
.PHONY: build

test: build
	docker-compose run --rm apache-exporter-php composer check
.PHONY: test

clean:
	docker-compose down --volumes
.PHONY: clean
