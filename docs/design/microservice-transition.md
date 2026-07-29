# Microservice Transition Contract

> Target architecture and migration rules for the transition from the current
> modular monolith to independently deployable services. This document does not
> claim that the current modules are already services.

---

## 1. Decision

The platform will evolve as a **monorepo containing multiple deployable Symfony
applications**. A service owns its runtime, database schema, migrations, worker
queues, scheduled jobs, and deployment artifact. Splitting into separate Git
repositories is explicitly deferred until service boundaries and contracts are
stable.

The first migration stage establishes the target directory structure and
dependency governance. It does not move every current `src/*` module or split
databases prematurely.

## 2. Target Repository Structure

```text
apps/
  commerce-service/       # Transitional home for Catalog, Ordering, Pricing
  store-service/
  inventory-service/
  payment-service/
  wallet-service/
  identity-service/
  content-service/
packages/
  platform-kernel/        # Framework-only HTTP/CRUD/observability utilities
  integration-contracts/  # Versioned transport-neutral event schemas
  test-support/
contracts/
  events/                 # Source schemas and compatibility fixtures
  openapi/                # Published service API contracts
deploy/
  compose/
  gateway/
docs/
tools/
```

Each `apps/*-service` is independently buildable and contains its own
`composer.json`, `src/Kernel.php`, `config/`, `migrations/`, `public/`, `bin/`,
`tests/`, and Docker build definition. A root Composer workspace may coordinate
local development, but it must not conceal an undeclared runtime dependency.

## 3. Current And Target Boundaries

| Target context | Current source | Initial position |
|---|---|---|
| Platform Kernel | Core | Shared library, never a business service |
| Identity & Access | Identity plus Wechat login adapter | Later extraction |
| Commerce | Trade plus Promotion | Transitional service; Catalog and Ordering separate later |
| Store Operations | Store | First extraction candidate |
| Inventory | Inventory | First extraction candidate; production enablement remains blocked by its safety checklist |
| Payments | Payment plus Wechat Pay adapter | After durable payment lifecycle events exist |
| Wallet/Ledger | Wallet | After Payment contracts are scalar and durable |
| Content/Media | Common plus Storage adapter | Later; split settings ownership from CMS first |

`Wechat` is a provider integration namespace, not a future bounded context. Its
login functions belong behind Identity adapters and its payment functions belong
behind Payment adapters. `Storage` is infrastructure, not a business service by
default.

## 4. Service Boundary Rules

The following rules apply before a module can become independently deployable:

1. A service owns its database and has no cross-service Doctrine association,
   foreign key, repository access, or transaction.
2. Durable cross-service references use UUIDs or documented immutable business
   keys, never another service's local integer primary key.
3. A service boundary accepts scalar commands/queries and emits scalar snapshots.
   It never exposes Doctrine entities, repositories, EntityManagers, Symfony
   requests/responses, or mutable in-process contexts.
4. A state-changing integration event is written to an Outbox in the producer's
   local transaction. Consumers use a durable Inbox (or equivalent consumed-event
   record) keyed by `eventId`.
5. Each consumer owns its queue, retry policy, dead-letter handling, worker, and
   scheduled jobs. A shared Doctrine Messenger queue is not a service boundary.
6. Shared packages may provide framework utilities, schemas, and test support.
   They MUST NOT contain business entities, repositories, or service-specific
   application logic.
7. Public routes remain stable through an API gateway or routing layer while
   ownership moves between services.

## 5. Integration Event Contract

All integration events use one versioned envelope:

```json
{
  "eventId": "uuid",
  "type": "store.order.accepted.v1",
  "version": 1,
  "aggregateType": "store_order",
  "aggregateId": "uuid",
  "occurredAt": "2026-07-29T12:00:00+00:00",
  "correlationId": "uuid",
  "causationId": "uuid",
  "payload": {}
}
```

The schema is owned by `packages/integration-contracts` or `contracts/events`,
not by a producer or consumer Symfony namespace. Changes require a new event
version and compatibility coverage.

## 6. Extraction Order And Gates

The preferred order is:

```text
Store -> Inventory -> Payment -> Wallet
```

Store and Inventory already use UUID/snapshot integration flows and Outbox/Inbox
patterns. They are candidates, not automatically production-ready services.
Inventory must retain its documented feature flag and safety restrictions until
its fulfillment, expiry, ordering, and concurrency guarantees are complete.

Payment, Wallet, Identity, Commerce, Promotion, Content, and provider adapters
must first be decoupled inside the monolith. Examples of required work:

- Replace shared `Identity\Entity\User` associations with external user UUIDs and
  local projections/snapshots.
- Replace Payment's synchronous Doctrine-entity events with Payment Outbox to
  Commerce Inbox events.
- Retire Trade's direct Wallet transfer path in favor of the Payment boundary.
- Replace Payment gateway and adjustment plugin APIs that expose `Invoice` or
  Symfony HTTP types with service-owned commands and result contracts.
- Replace Promotion's shared mutable pricing context with a scalar quote request
  and deterministic result before considering remote execution.

## 7. First-Stage Deliverables

Directory restructuring is considered complete only when all of the following
exist, even before business code is moved:

- The target `apps/`, `packages/`, `contracts/`, `deploy/`, and `tools/` ownership
  model is documented and reflected in repository tooling.
- Architecture checks reject forbidden cross-service imports and entity leaks.
- Existing Trade, Store, and Inventory events share the envelope in section 5.
- A service template defines independent Kernel, configuration, migration, queue,
  worker, scheduler, Docker, test, static-analysis, and CI ownership.
- The legacy modular-monolith test suite remains as a characterization suite
  until the relevant service extraction is complete.

## 8. Non-Goals

- Moving directories without enforcing a deployable boundary.
- Creating empty service applications for every current module.
- Extracting `Core` as a network service.
- Splitting historical migrations by filename; mixed legacy migrations remain for
  rebuilding the monolith, while extracted services start from validated schema
  baselines and new service-local migration histories.
