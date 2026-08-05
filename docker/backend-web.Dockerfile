FROM nginxinc/nginx-unprivileged:1.27-alpine
COPY docker/backend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --chown=nginx:nginx apps/backend/public /var/www/atlas/public
USER nginx
EXPOSE 8080
