.PHONY: up down seed test lint verify-lockfiles

up:
	@docker compose up -d

down:
	@docker compose down

seed:
	@pnpm run seed

test:
	@pnpm test

lint:
	@pnpm lint

verify-lockfiles:
	@node scripts/verify-lockfiles.mjs
