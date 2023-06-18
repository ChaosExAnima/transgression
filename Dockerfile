FROM --platform=linux/amd64 wordpress:php8.2-fpm-alpine

RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli-nightly.phar; \
	# Make it writeable so nightlys can be updated regularly
	chmod +wx wp-cli-nightly.phar; \
	mv wp-cli-nightly.phar /usr/local/bin/wp

RUN set -ex; \
	apk update; \
	apk add libxml2-dev vim

RUN set -ex; \
	docker-php-ext-install -j "$(nproc)" \
	pdo_mysql \
	soap
