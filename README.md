# CRUD Platform

CRUD Platform is the evolution of `crud-skeleton`: a Symfony 8.1 backend that
starts from a modular CRUD foundation and is being prepared for a
multi-application microservice architecture.

> Chinese (Simplified): [README.zh-cn.md](README.zh-cn.md) · Chinese (Traditional): [README.zh-hant.md](README.zh-hant.md) · Japanese: [README.ja.md](README.ja.md)

## Project Goal

The target is a **monorepo with independently deployable Symfony services**.
Each service will own its Kernel, configuration, database and migrations, queues,
workers, scheduled jobs, Docker image, tests, and CI.

The repository is currently a **modular monolith**, not a completed microservice
system. It has one Kernel, Composer project, container, database, migration chain,
Messenger queue, worker, scheduler, and Docker image. The `Trade -> Store ->
Inventory` Outbox/Inbox flow is the first extraction seam.

The transition rules, target directory structure, and extraction gates are in
[Microservice Transition Contract](docs/design/microservice-transition.md).

## Current Capabilities

- Symfony 8.1, PHP 8.4+, Doctrine ORM, MySQL 8, SQLite test environment.
- Identity and access: RS256 JWT, refresh-token rotation, OTP, password login,
  WeChat login adapters.
- CMS, catalog/order workflow, store operations, inventory reservation, invoices,
  wallet ledger, promotion DSL, and media storage modules.
- Versioned Trade/Store/Inventory integration events with Outbox/Inbox patterns.
- OpenAPI UI at `/api/doc`, PHPUnit, PHPStan Level 8, Rector type checks, and
  Docker Compose development environment (22 services).

Inventory is implemented but remains disabled by default and is not production-ready.
See its safety restrictions in [Inventory design](docs/design/bundles/inventory.md).

## Quick Start

Docker is the supported local path. From the repository root, Docker is the only
required prerequisite:

```bash
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec store-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec inventory-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec payment-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec wallet-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec identity-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec common-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec trade-app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:identity:user:create admin@example.com admin 'P@ssw0rd' --admin

curl -X POST http://localhost:8080/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"identifier":"admin@example.com","password":"P@ssw0rd"}'
```

- API: `http://localhost:8080`
- Store API: `http://localhost:8081`
- Inventory API: `http://localhost:8082`
- Payment runtime smoke: `http://localhost:8083` (not traffic-ready)
- Wallet runtime smoke: `http://localhost:8084` (not traffic-ready)
- Identity runtime smoke: `http://localhost:8085` (not traffic-ready)
- Common runtime smoke: `http://localhost:8086` (not traffic-ready)
- Trade runtime smoke: `http://localhost:8087` (not traffic-ready)
- OpenAPI: `http://localhost:8080/api/doc`
- Worker and scheduler logs: `docker compose logs -f worker scheduler`

For troubleshooting and native-PHP notes, see [QUICKSTART.md](QUICKSTART.md).

## Architecture Direction

| Target context | Current source | Position |
|---|---|---|
| Platform Kernel | `Core` | Shared framework library, not a service |
| Commerce | `Trade`, `Promotion` → `apps/trade` | Extracted; monolith hosts during transition |
| Store Operations | `Store` → `apps/store` | Extracted; monolith hosts during transition |
| Inventory | `Inventory` → `apps/inventory` | Extracted; monolith hosts during transition; safety-gated |
| Payments | `Payment` → `apps/payment`, WeChat Pay adapter | Extracted; gateways owned by Payment app |
| Wallet/Ledger | `Wallet` → `apps/wallet` | Extracted; monolith hosts during transition |
| Identity & Access | `Identity` → `apps/identity`, WeChat login adapter | Extracted; monolith hosts during transition |
| Content/Media | `Common`, `Storage` → `apps/common` | Extracted; monolith hosts during transition |

Before any service is extracted, it must have scalar cross-service contracts,
no cross-service Doctrine relations, an Outbox/Inbox flow where needed, and its
own queue/runtime/deployment ownership.

## Development

```bash
./vendor/bin/phpunit
composer deptrac
composer phpstan
composer rector:types:check
composer coverage                # run all suites + aggregate coverage >= 90%
mkdocs build --strict
```

Use PHP 8.4+ for local commands. The repository's test suite is retained as a
characterization suite while extraction proceeds. Seven standalone app test suites
(common, identity, inventory, payment, store, trade, wallet) run independently
alongside root integration tests with aggregated line coverage at 91.36%.

## Documentation

- [Microservice Transition Contract](docs/design/microservice-transition.md)
- [System Architecture](docs/design/system-architecture.md)
- [System Contracts](docs/design/system-contracts.md)
- [Module Design](docs/design/module-design.md)
- [AI Context](docs/ai/context.md)
- [Documentation site](https://immane.github.io/crud-skeleton)

## License

Apache-2.0. See [LICENSE](LICENSE).
