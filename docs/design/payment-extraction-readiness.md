# Payment Extraction Readiness

> Payment remains hosted by the monolith. Its outgoing invoice lifecycle has
> been made durable so that the eventual Payment application can be extracted
> without retaining a synchronous Payment-to-Trade dependency.

## Completed Expand Stage

- `payment_outbox_message` records paid, failed, cancelled, and refunded invoice
  lifecycle events in the same transaction as the invoice state transition.
- `app:payment:outbox:publish` publishes the four neutral v1 contracts through
  the existing async transport:
  - `payment.invoice.paid.v1`
  - `payment.invoice.failed.v1`
  - `payment.invoice.cancelled.v1`
  - `payment.invoice.refunded.v1`
- Trade consumes these contracts through an idempotent `trade_consumed_event`
  Inbox. It verifies the source order, linked invoice identity, and paid amount
  and currency before applying the existing order workflow transitions.
- Payment events carry invoice identifiers, source binding, status, money,
  currency, payment metadata, and lifecycle timestamps only. Gateway raw data
  and invoice `extra_data` never cross the integration boundary.
- The root scheduler publishes Payment Outbox rows alongside Trade, Store, and
  Inventory. It clears its production Symfony cache at startup so newly deployed
  command services are discovered.

## Transition Safety

The existing synchronous `OrderInvoiceListener` remains temporarily enabled.
It preserves current API behavior while the durable path is observed. The Trade
Inbox makes the asynchronous copy safe to replay: it records the event ID and
payload hash, treats an identical retry as a no-op, and rejects an event ID
reused with a different payload.

Do not remove the synchronous listener until the following have been observed:

1. Payment Outbox publishing and Trade consumer processing are healthy for a
   bounded production period.
2. Paid, failed, cancelled, partial-refund, and full-refund paths have all been
   exercised through the durable path.
3. Failed transport rows and any in-flight events have been replayed.
4. The resulting eventual-consistency behavior is accepted by the payment and
   order API clients.

## Remaining Extraction Work

1. Introduce the shared broker and schema-versioned serializer required for an
   independent Payment worker and database.
2. Disable and remove the synchronous `OrderInvoiceListener` after the durable
   path has met the observation criteria.
3. Extract Payment source, its baseline schema, and a dedicated
   `apps/payment` runtime using the Store and Inventory transition-host pattern.
4. Move Payment HTTP traffic, worker, and scheduler behind Gateway shadow
   routing, then remove the monolith host assembly.
5. Extract Wallet only after its Payment gateway and adjustment contracts no
   longer expose Payment persistence or framework types.
