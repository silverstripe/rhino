FROM ubuntu:22.04

VOLUME ["/var/www"]

ENV TZ=Pacific/Auckland
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Using apt-get instead of apt to prevent warning about apt not having a stable CLI interface

# Increment this to force an update of the apt deps
RUN echo 123 > /dev/null

# Make all php versions other than the current main version (7.4) available
RUN apt-get update && apt-get install -y software-properties-common
RUN add-apt-repository -y ppa:ondrej/php
RUN add-apt-repository -y ppa:ondrej/apache2

# Set this arg so that apt-utils does not cause other warnings
ARG DEBIAN_FRONTEND=noninteractive

# bcmath extension and chromium gave warnings when installing if this was missing
RUN apt-get install -y apt-utils

RUN apt-get install -y apache2

RUN apt-get install -y libapache2-mod-php8.1
RUN apt-get install -y php8.1
RUN apt-get install -y php8.1-bcmath
RUN apt-get install -y php8.1-cli
RUN apt-get install -y php8.1-curl
RUN apt-get install -y php8.1-dev
RUN apt-get install -y php8.1-dom
RUN apt-get install -y php8.1-gd
RUN apt-get install -y php8.1-intl
RUN apt-get install -y php8.1-ldap
RUN apt-get install -y php8.1-mbstring
RUN apt-get install -y php8.1-mysql
RUN apt-get install -y php8.1-tidy
RUN apt-get install -y php8.1-xdebug
RUN apt-get install -y php8.1-zip

# Install other packages
RUN apt-get install -y nano wget unzip jq

# Increment echo <n> to get a more recent version of chrome
RUN echo 1 > /dev/null

COPY docker_apache_default /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

EXPOSE 80
EXPOSE 443

# Default www-data user/group id is 33, change it to 1000 to match the steve user on host
# https://jtreminio.com/blog/running-docker-containers-as-current-host-user/#ok-so-what-actually-works
ARG USER_ID=1000
ARG GROUP_ID=1000
RUN userdel -f www-data &&\
    if getent group www-data ; then groupdel www-data; fi &&\
    groupadd -g ${GROUP_ID} www-data &&\
    useradd -l -u ${USER_ID} -g www-data www-data &&\
    install -d -m 0755 -o www-data -g www-data /home/www-data &&\
    chown --changes --silent --no-dereference --recursive \
          --from=33:33 ${USER_ID}:${GROUP_ID} \
        /home/www-data

# Docker script - anything else that's just easier to write in raw bash than dockerfile
COPY docker_script /usr/local/bin/docker_script
RUN chmod +x /usr/local/bin/docker_script
CMD ["/usr/local/bin/docker_script"]
