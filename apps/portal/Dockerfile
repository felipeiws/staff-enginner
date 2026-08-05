# syntax=docker/dockerfile:1.7
FROM node:22.22.2-alpine AS build
WORKDIR /workspace
RUN corepack enable
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml tsconfig.base.json ./
COPY packages/contracts/package.json packages/contracts/package.json
COPY packages/sdk/package.json packages/sdk/package.json
COPY apps/portal/package.json apps/portal/package.json
RUN pnpm install --frozen-lockfile --filter @atlas/portal...
COPY packages ./packages
COPY apps/portal ./apps/portal
RUN pnpm --filter @atlas/portal build

FROM nginxinc/nginx-unprivileged:1.27-alpine AS runtime
COPY apps/portal/docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build --chown=nginx:nginx /workspace/apps/portal/dist /usr/share/nginx/html
USER nginx
EXPOSE 8080
