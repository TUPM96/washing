FROM bitnami/laravel:latest

RUN install_packages imagemagick php-imagick supervisor

COPY ./laravel/php.ini /opt/bitnami/php/lib/php.ini
COPY ./laravel/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord"]
