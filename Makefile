.PHONY: dev test e2e status logs stop ci ci-quick matrix package-check integration release-dry-run release-build wiki-sync
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

# Local CI / CD entrypoints
ci:
	@./scripts/ci all

ci-quick:
	@./scripts/ci quick

matrix:
	@./scripts/ci matrix

package-check:
	@./scripts/ci package

integration:
	@./scripts/ci integration

release-dry-run:
	@python3 scripts/release/publish.py --dry-run

release-build:
	@python3 scripts/release/release.py build && python3 scripts/release/release.py verify

wiki-sync:
	@./scripts/sync-wiki --dry-run
