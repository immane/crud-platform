# CRUD Platform — Development Manual

CRUD Platform (also called CRUD Skeleton) is a Symfony 8.1 API backend providing
a multi-application monorepo for e-commerce and CMS operations. It features JWT
authentication (RS256), a dynamic expression-based query engine, an event-driven
integration model (Outbox/Inbox), and eight independently bootable applications.

## Table of Contents

| # | Document | Description |
|---|----------|-------------|
| 1 | [Architecture](architecture.md) | Service topology, layer architecture, monorepo layout, extraction status, design patterns |
| 2 | [Getting Started](getting-started.md) | Docker/native setup, port table, JWT keys, verification, troubleshooting |
| 3 | [Project Structure](project-structure.md) | Directory tree, naming conventions, module structure pattern, where to put new code |
| 4 | [Development Workflow](development-workflow.md) | Branching model, coding standards, static analysis gates, PR checklist, CI pipeline |
| 5 | [Testing](testing.md) | Test structure, per-app suites, running tests, coverage gates, writing tests |
| 6 | [Integration Events](integration-events.md) | Outbox/Inbox pattern, envelope structure, publishing/consuming, correlation tracing, backfill commands |
| 7 | [Database & Migrations](database-and-migrations.md) | Doctrine conventions, repository pattern, migration workflow, per-app baselines, schema conventions |
| 8 | [Deployment](deployment.md) | Docker Compose topology, service tables, dev/prod overlays, env vars, building/running |
| 9 | [Extracting a Service](extracting-a-service.md) | Step-by-step extraction guide, pre-extraction checklist, verification, common pitfalls |
| 10 | [Internationalization](i18n.md) | Translation architecture, locale detection, adding keys, adding a locale |

### Additional Resources

- **[Design Contracts](../design/)** — Formal architecture contracts, module designs, API designs
- **[OpenAPI Docs](../openapi/)** — Frontend integration guides for order/payment flows
- **[AI Context](../ai/context.md)** — Machine-readable codebase snapshot for AI assistants
- **[MkDocs Site](https://crud-platform.readthedocs.io/)** — Rendered documentation site (if deployed)
