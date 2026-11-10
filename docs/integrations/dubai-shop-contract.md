# Dubai Shop integration contract

**Status:** approved technical contract for phased implementation  
**Contract version:** v1  
**System of record:** Dubai Shop (Clásico Dubai)  
**Consumer and publishing orchestrator:** SocialPublicator

## 1. Purpose and boundaries

Dubai Shop is the sole authority for products, variants, sale prices, warehouses, and inventory. SocialPublicator consumes the catalog and events to select campaign items and prepare social publications. The applications must not connect directly to each other's databases.

This document defines the planned contract. It does not claim that the endpoints, tables, or workers already exist.

The integration must preserve the existing local-product workflow, Publication scheduling, destinations, bots, and execution tracking.

## 2. External catalog model

SocialPublicator must maintain a separate external catalog mirror. It must not create or overwrite external records in the local `Product` model and must not map Dubai Shop inventory into local `stock`, `precio_costo`, `precio_mayorista`, or `precio_minorista` fields.

The future model may use source-scoped entities such as:

- Integration source/connection.
- External catalog item and variant.
- External warehouse and per-warehouse availability.
- Campaign-to-external-item selection.
- External item publication pivot or a normalized publication-item abstraction.

Every external record is identified by the stable UUID issued by Dubai Shop plus its source connection. Local SocialPublicator products remain independent and continue using their current relations.

## 3. Source API contract

Dubai Shop will expose the versioned read API:

`/api/integrations/social-publicator/v1`

| Method | Route | Purpose |
|---|---|---|
| GET | `/items` | Full or incremental paginated catalog. |
| GET | `/items/{external_id}` | Current item state. |
| GET | `/warehouses` | Eligible warehouses and stable external identifiers. |

The first synchronization is a full cursor-paginated import. Subsequent synchronizations continue from an opaque cursor. A periodic full reconciliation repairs missed or delayed events.

Catalog items include public product/variant data, sale price in integer minor units, ISO currency, public image URL, per-warehouse availability, revision, and update timestamp. Acquisition cost, internal minimum sale price, customer data, orders, and payment information are never part of this contract.

## 4. Warehouse selection per campaign

Each campaign supports both agreed modes:

- `all`: all active Dubai Shop warehouses enabled for online fulfillment. Newly eligible warehouses are included automatically.
- `selected`: only the campaign's explicitly selected eligible warehouses.

SocialPublicator calculates campaign availability by summing the latest `available_quantity` values for the warehouses included by that campaign. It must not infer campaign depletion from a global stock total.

Dubai Shop's per-warehouse availability is:

`max(0, quantity_on_hand - quantity_reserved - quantity_blocked)`

A product is considered available in a campaign if at least one eligible selected item (simple product or active variant) has positive availability in the campaign's warehouse set.

## 5. Event receiver

Dubai Shop will deliver events to a dedicated integration endpoint, separate from the existing bot API:

`POST /api/integrations/dubai-shop/v1/events`

The existing `VerifyBotApiToken` middleware and WBot token must not be reused as the integration's authentication mechanism.

Supported v1 event types:

- `catalog.item_published`
- `catalog.item_unpublished`
- `catalog.item_updated`
- `inventory.changed`
- `price.changed`
- `warehouse.updated`

The `inventory.changed` event carries per-warehouse availability. SocialPublicator derives campaign-specific depletion and replenishment transitions from the campaign's warehouse selection.

### Event envelope

```json
{
  "event_id": "UUID",
  "event_type": "inventory.changed",
  "contract_version": "v1",
  "aggregate_external_id": "UUID",
  "revision": 13,
  "occurred_at": "2026-11-09T12:01:00Z",
  "data": {
    "item_external_id": "UUID",
    "availability": {
      "warehouses": [
        {
          "warehouse_external_id": "UUID",
          "available_quantity": 0
        }
      ]
    }
  }
}
```

The event receiver validates the HMAC signature, timestamp, payload size, schema version, event ID, and revision before accepting the event. It returns an accepted response only after the event is durably queued or stored for processing; this acknowledgement does not mean the social publication has succeeded.

## 6. Event processing and idempotency

- Deduplicate by `event_id`.
- Track the latest accepted revision per external item and ignore stale out-of-order updates.
- Preserve the same event ID and body on delivery retries.
- Process events asynchronously with retry/backoff and observable failures.
- Reconcile the full catalog periodically against Dubai Shop.
- Revalidate the latest item state, sale price, and campaign availability immediately before sending a publication.

A delayed event must never cause an older price or stock value to overwrite a newer revision.

## 7. Agreed publication behavior

### Availability and depletion

When campaign availability becomes positive, the item is eligible for normal campaign selection. When campaign availability transitions from positive to zero, SocialPublicator publishes an out-of-stock notification and removes the item from availability groups. One notification is allowed per effective depletion transition, campaign, and destination.

A product with stock in any warehouse included in the campaign must not be announced as depleted, even if it is out of stock elsewhere.

### Replenishment

When campaign availability transitions from zero to positive, SocialPublicator automatically publishes a replenishment notification and reintroduces the item into normal campaign selection. Duplicate or replayed events must not create duplicate notifications.

### Immediate price updates

A price change queues a high-priority publication outside the ordinary campaign schedule. It uses the current sale price from Dubai Shop, never an acquisition cost. Rapid successive changes are coalesced so obsolete prices are not published; the final content is revalidated immediately before delivery.

### Grouped presentation

External products are published in grouped messages by campaign and destination, with optional category grouping. Groups are split to satisfy each channel's text/media limits. If only one item is ready, it may be sent as a one-item group rather than delaying an immediate price or replenishment notification indefinitely.

## 8. Security

- Use HTTPS only.
- Catalog reads from Dubai Shop use a dedicated Bearer token with least-privilege read scopes.
- Event delivery uses a separate HMAC SHA-256 signing key over the timestamp and raw request body, with key ID and replay protection.
- Secrets are stored outside source control and encrypted at rest where persisted.
- Support key rotation, rate limiting, audit logs, payload-size limits, and safe error responses.
- WhatsApp/Facebook credentials remain in SocialPublicator and the existing bot environments; they are never sent to Dubai Shop.

## 9. Reuse of existing SocialPublicator capabilities

The integration must reuse the existing Publication scheduling, destinations, WhatsApp/Facebook group relations, bot command delivery, and `PublicationExecutionService` idempotency/locking mechanisms wherever applicable.

The current local Product model and its inventory fields remain dedicated to SocialPublicator-managed local products. External catalog items require their own persistence and publication association, without changing the meaning of existing local-product relations.

## 10. Implementation sequence

1. Contract and documentation (this phase).
2. Dubai Shop read API, external identifiers, and dedicated credentials.
3. SocialPublicator event receiver, external catalog mirror, and reconciliation.
4. Campaign and warehouse configuration.
5. Intelligent selection and grouped composition.
6. Depletion, replenishment, and immediate price event handling.
7. Delivery through existing destinations and bots.
8. End-to-end tests, CI validation, and gradual activation.

Each phase must have automated tests and passing CI before it is considered ready for integration.
