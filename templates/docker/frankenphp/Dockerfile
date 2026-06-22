# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.3
FROM dunglas/frankenphp:1-php${PHP_VERSION}-alpine AS base

# hadolint ignore=DL3018
RUN apk upgrade --no-cache && \
    apk add --no-cache bash curl git unzip && \
    install-php-extensions \
        bcmath \
        exif \
        gd \
        intl \
        mysqli \
        opcache \
        pdo_mysql \
        uopz \
        zip

FROM base AS dev

RUN install-php-extensions xdebug

ENV SERVER_NAME=:8080
WORKDIR /app
EXPOSE 8080
