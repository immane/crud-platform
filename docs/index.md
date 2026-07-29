# CRUD Platform

CRUD Platform evolves the `crud-skeleton` Symfony backend into a
multi-application microservice platform.

## Current Status

The codebase is currently a **modular monolith**:

- One Symfony Kernel, Composer project, service container, database, migration chain,
  Messenger queue, worker, scheduler, Docker image, and test suite.
- Domain modules include Identity, CMS, Commerce, Store, Inventory, Payment, Wallet,
  Promotion, WeChat adapters, and Storage adapters.
- The `Trade -> Store -> Inventory` Outbox/Inbox flow is the strongest existing
  extraction seam.

This is not yet a set of independently deployable services. In particular, shared
Doctrine associations, synchronous payment/wallet calls, and in-process plugin APIs
must be removed before the affected modules are extracted.

## Target

The target is a monorepo with independently deployable Symfony applications. Each
service owns its Kernel, configuration, database and migrations, queue, worker,
scheduler, image, tests, and CI.

The first extraction candidates are Store and Inventory. Inventory remains disabled
by default and is not production-ready until its safety checklist is complete.

Read the [Microservice Transition Contract](design/microservice-transition.md) for
the target directory structure, service-boundary rules, event envelope, and
extraction gates.

## Local Run

```bash
docker compose up -d --build
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec app php bin/console app:identity:user:create admin@example.com admin 'P@ssw0rd' --admin
```

- API: `http://localhost:8080`
- OpenAPI: `http://localhost:8080/api/doc`
- Mailpit: `http://localhost:8025`

## Documentation

- [Microservice Transition](design/microservice-transition.md)
- [System Architecture](design/system-architecture.md)
- [System Contracts](design/system-contracts.md)
- [Module Design](design/module-design.md)
- [API Design](design/api-design.md)
- [AI Context](ai/context.md)

## Quality Checks

```bash
./vendor/bin/phpunit
composer deptrac
composer phpstan
composer rector:types:check
```

Use PHP 8.4+ for local commands.
