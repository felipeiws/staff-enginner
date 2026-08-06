# syntax=docker/dockerfile:1.7
FROM ghcr.io/astral-sh/uv:0.6.5-python3.12-bookworm-slim AS dependencies
WORKDIR /app
COPY apps/worker/pyproject.toml apps/worker/uv.lock ./
RUN uv sync --locked --no-dev --no-install-project
COPY apps/worker ./
RUN uv sync --locked --no-dev

FROM python:3.12-slim-bookworm AS runtime
RUN groupadd --system --gid 10001 atlas && useradd --system --uid 10001 --gid atlas --create-home atlas
WORKDIR /app
COPY --from=dependencies --chown=atlas:atlas /app/.venv ./.venv
COPY --chown=atlas:atlas apps/worker ./
ENV PATH="/app/.venv/bin:$PATH" PYTHONUNBUFFERED=1
USER atlas
EXPOSE 8081
CMD ["atlas-worker"]
