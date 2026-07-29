# Store Extraction Readiness

> An implementation inventory for extracting Store into `apps/store` without
> changing public API routes or losing existing queue and database safety.

## Current Scope

Store currently contains 32 source files and 13 Store-specific test files. Its
owned runtime responsibilities are:

- Store, membership, StoreOrder, Inbox, Outbox, and cancellation-tombstone data.
- App, manage, and staff HTTP endpoints under the existing `/api/v1` gateway
  prefix.
- Trade and Inventory integration consumers.
- Store Outbox publisher and correlation backfill command.

The intended owned data set is:

```text
store
store_membership
store_order
store_consumed_event
store_outbox_message
store_trade_order_cancellation
```

`store_trade_order_cancellation` is currently created in the Inventory migration
only because it was introduced with Inventory flow work. It belongs to Store and
must move into the Store service schema baseline before an independent database
cutover.

## Dependency Inventory

| Dependency | Current use | Extraction disposition |
|---|---|---|
| `App\Core` | REST controller, view traits, BaseService, UUID | Provided by `packages/platform-kernel`; Store may depend on this library. |
| `CrudPlatform\IntegrationContracts` | All new async messages | Keep; this is the approved cross-service dependency. |
| `App\Trade\Message` / `App\Inventory\Message` | Legacy native-Messenger compatibility adapters | Temporary. Retain only until old `async`/`failed` records are drained or migrated. |
| `App\Trade\DTO\StoreContext` and resolver interface | Synchronous `X-Store-Code` lookup during Trade order creation | Blocker. Replace with a scalar Store directory HTTP contract or move the resolver to Commerce before Store becomes independent. |
| `App\Identity\Entity\User` | App/staff controllers used it only to read UUID | Resolved. Store now depends on `App\Core\Security\UserUuidPrincipalInterface`, implemented by Identity User. |
| `config/routes.yaml` | Imports `src/Store/Controller` | Move to Store-owned routes; preserve public paths through the gateway. |
| Root scheduler/worker | Runs Store Outbox publishing | Move to Store-owned worker and scheduler after Store application is independently runnable. |

No Store Entity or Repository imports another module's Entity or Repository. The
Deptrac baseline no longer contains a Store-to-Identity persistence exemption.

## Preconditions For Source Move

1. Deploy the neutral-carrier release, run the Outbox metadata migration, and
   complete bounded correlation backfills in production.
2. Drain or migrate legacy native-PHP queue records before Store owns its own
   worker queue. Do not remove legacy wrapper adapters earlier.
3. Keep the monolith consuming `packages/platform-kernel` before Store introduces
   an independent Kernel.
4. Replace Trade's in-process `StoreContextResolverInterface` plugin with a
   documented scalar boundary.
5. Build a Store schema baseline from the owned tables, including the cancellation
   tombstone, and rehearse copy, increment, and reconciliation steps.

## Move Sequence

```text
packages/platform-kernel
  -> monolith consumes package
  -> apps/store skeleton with independent Kernel/config/composer/tests (complete)
  -> Store source, routes, commands, and tests move to apps/store
  -> Store schema baseline and shadow database verification
  -> Store worker/scheduler and gateway shadow routing
  -> read traffic, then write traffic, then database cutover
```

The first source move must not alter public paths, table names, or JSON request and
response formats. Gateway ownership changes only after Store has passed data and
message reconciliation.

## Application Skeleton

`apps/store` is an independently installable Symfony application with its own
Composer lock, `App\Store\Kernel`, configuration, migration directory, tests, and
runtime entry points. It maps only `App\Store\Entity` and intentionally contains no
monolith Store source yet. A successful `php bin/console about --env=test` proves
that the application boots without loading the root application's `src/Store` or
Doctrine mapping.
