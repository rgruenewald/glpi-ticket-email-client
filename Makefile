.PHONY: dev test e2e status logs stop
.DEFAULT_GOAL := dev

dev:
	@./scripts/dev interactive

test:
	@./scripts/dev test

e2e:
	@./scripts/dev e2e

status:
	@./scripts/dev status

logs:
	@./scripts/dev logs

stop:
	@./scripts/dev stop
