# Base image will be Ubuntu 24.04 LTS

FROM ubuntu:24.04

# Arguments

ARG systemuser="efi"
ARG gitemail=""
ARG gitname=""

# Expose port 8000

EXPOSE 8000

# Install system dependencies

RUN apt-get update -y
RUN apt-get upgrade -y
RUN apt-get install -y \
	zip \
	wget \
	curl \
	php8.3 \
	php8.3-curl \
	php8.3-common \
	php-tokenizer \
	php8.3-mysql \
	php8.3-sqlite3 \
	php8.3-xml \
	git \
	mysql-server \
	sqlite3

# Run this under development environment

RUN apt-get install -y \
	nano \
	bash-completion

RUN sed -i 's/^/#/g' /etc/apt/apt.conf.d/docker-clean
RUN apt-get update -y

RUN useradd -m $systemuser

RUN mkdir -p /data
RUN chmod 777 /data

USER $systemuser

# Installing composer

RUN mkdir -p /data/installers
WORKDIR /data/installers

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
RUN php composer-setup.php
RUN mkdir -p /home/$systemuser/.local/bin
RUN cp -p composer.phar /home/$systemuser/.local/bin/composer

# Installing symfony

RUN wget https://get.symfony.com/cli/installer -O install-symfony.bash
RUN bash install-symfony.bash

ENV PATH="/home/$systemuser/.symfony5/bin:/home/$systemuser/.local/bin:${PATH}"
RUN echo ${PATH}

# Create a fresh project

RUN git config --global user.name $gitname
RUN git config --global user.email $gitemail

WORKDIR /data

#RUN symfony new efi-website --version="7.1" --webapp

# Run the server

#CMD ["symfony", "server:start", "--no-tls", "--dir=/data/efi-website", "--port=8000", "--allow-http", "--listen-ip=0.0.0.0"]
