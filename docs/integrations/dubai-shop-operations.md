# Dubai Shop integration operations

## Configuration

Configure these variables in SocialPublicator's environment. Do not commit real credentials.

```dotenv
DUBAI_SHOP_API_BASE_URL=https://your-dubai-shop-host
DUBAI_SHOP_API_TOKEN=
DUBAI_SHOP_WEBHOOK_KEYS='{"key-2026-01":"replace-with-a-long-random-secret"}'
DUBAI_SHOP_WEBHOOK_TOLERANCE_SECONDS=300
DUBAI_SHOP_TIMEOUT=15
```

`DUBAI_SHOP_API_BASE_URL` is the origin of the Dubai Shop site, without the `/api` suffix. The API token is issued on Dubai Shop with:

```bash
php artisan social-publicator:token --name="SocialPublicator" --expires=365
```

The webhook key map supports overlapping key IDs during rotation. Configure the same key ID and secret in Dubai Shop when its outbound webhook delivery is enabled. Signature headers are `X-DubaiShop-Key-Id`, `X-DubaiShop-Timestamp` (Unix seconds), and `X-DubaiShop-Signature` (`sha256={hex-hmac}`). The signed message is `{timestamp}.{raw-request-body}`.

## Event receiver

Dubai Shop sends events to:

`POST /api/integrations/dubai-shop/v1/events`

The endpoint validates the signature and timestamp, validates the v1 envelope, stores each event in `integration_event_inbox`, and acknowledges it with HTTP 202. The unique `event_id` prevents duplicate event processing. Accepted events are queued and update the external catalog mirror; acknowledgement does not mean a social publication has been sent.

## Catalog reconciliation

Run a full reconciliation manually:

```bash
php artisan social-publicator:sync-dubai-catalog
```

The command synchronizes all eligible warehouses and all cursor-paginated catalog items. Items no longer returned by Dubai Shop are marked inactive in the mirror; local SocialPublicator products are not changed.

The same command is scheduled hourly and does nothing until the Dubai Shop API URL and token are configured. Configure a persistent Laravel queue worker to process webhook events.

The existing `publications:mark-stale-executions` command remains scheduled every five minutes. It marks interrupted publication attempts as having an unknown external result and deliberately does not retry them automatically; verify Facebook or WhatsApp before any manual retry.

## Data ownership

External Dubai Shop items are stored in `external_catalog_items` and warehouses in `external_catalog_warehouses`. They are not copied into the local `products` table and never change local stock or local costs. Dubai Shop remains authoritative for sale prices and availability.


## Campaign configuration (phase 4)

Manage campaigns in Filament under **Integraciones → Campañas sociales**. Each campaign stores:

- An explicit list of Dubai Shop products and variants selected from the external catalog mirror. Only active and visible mirrored items can be selected.
- One or more eligible warehouses. Availability rules for the campaign must use only these selected warehouses.
- Publication format (grouped or individual), daily publication cap, and minimum interval.
- Out-of-stock, replenishment, and price-change preferences. Defaults preserve the approved integration decisions: grouped publications, publish replenishment notices, and publish price changes immediately.

Campaign configuration is intentionally separate from the local SocialPublicator products and inventory. It does not copy external stock into local stock or publish anything by itself. Automatic product selection, rotation, frequency enforcement, and actual social publication are implemented in later phases. Keep the campaign inactive until those phases are enabled and validated.


## Campaign selection engine (phase 5)

The `social-publicator:select-campaign-items` command runs every five minutes. It records planned selection groups only; it does not send content to social networks. The engine supports manual campaign lists or automatic selection from the active/visible Dubai Shop mirror, warehouse-scoped availability, least-recent/round-robin/random rotation, grouped or individual mode, daily caps, minimum intervals, weekday and local-time windows, and a configured timezone.

Each selection stores the selected item, quantity available in the campaign's selected warehouses, the warehouse IDs contributing stock, the selection group, and its planned status. Warehouse eligibility is rechecked at selection time. The later publishing phase must still revalidate current availability with Dubai Shop before sending.


## Campaign event triggers (phase 6)

When an `inventory.changed` or `price.changed` webhook is processed, SocialPublicator refreshes the external catalog item and evaluates active campaigns. Inventory transitions are calculated independently for each campaign's currently active, online-eligible selected warehouses, so stock in an unselected warehouse cannot suppress an out-of-stock trigger or create a false restock. Campaign settings determine whether an out-of-stock, replenishment, or immediate price-change trigger is recorded.

Triggers are stored in `external_catalog_campaign_triggers` with the source event ID and a unique campaign/type key. Reprocessing the same source event cannot create duplicate triggers. These records are pending work for the later publishing phase; this phase does not send to Facebook or WhatsApp.

## Campaign publication dispatch (phase 7)

The **social-publicator:dispatch-campaign-publications** command consumes pending campaign selections and event triggers. It refreshes each external item from Dubai Shop immediately before creating a publication, verifies that the item remains active and visible, and calculates stock only across the campaign's selected warehouses that are still active and enabled for online fulfillment.

A successful dispatch creates a record in the existing **publications** table and attaches the configured active WhatsApp/Facebook groups. Existing bots continue to fetch and execute these records through the current bot API; execution IDs, per-target tracking, and unknown-result protections remain in effect. Unique campaign origin keys prevent a retry from creating duplicate publications.

The command runs every minute only after the explicit campaign-dispatch flag and the API credentials are configured. A pending trigger or selection is not proof that a post was sent; inspect the corresponding campaign record, publication, and bot execution state.

## Gradual activation and production checklist (phase 8)

Campaign publication is disabled by default. Keep **DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED=false** until all prerequisites are verified.

1. Configure the Dubai Shop API URL and issue a dedicated read-only API token with the catalog:read ability.
2. Configure **DUBAI_SHOP_WEBHOOK_KEYS** with a long random secret and the intended key ID. Configure the exact same key ID and secret in Dubai Shop's **SOCIAL_PUBLICATOR_WEBHOOK_KEY_ID** and **SOCIAL_PUBLICATOR_WEBHOOK_SECRET**.
3. On Dubai Shop, keep **SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=false** while validating the read-only catalog sync and campaign setup.
4. Run php artisan social-publicator:sync-dubai-catalog; verify products, variants, images, prices, and warehouse eligibility in the mirror.
5. Create one inactive test campaign, choose a small product set, one eligible warehouse, and one controlled test group. Verify its channel destinations and posting window.
6. Start and verify a persistent Laravel queue worker on both applications. Confirm the scheduler is running and inspect failed jobs and outbox/inbox backlogs.
7. Enable signed event delivery on Dubai Shop. Confirm new events arrive in the SocialPublicator inbox and are processed once.
8. Enable **DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED=true** for SocialPublicator only after the preceding checks. Activate one test campaign and observe its first publication and bot execution before expanding destinations or campaigns.

To pause social publication immediately, set **DUBAI_SHOP_CAMPAIGN_DISPATCH_ENABLED=false** and stop/disable the relevant bot workers if an execution is already in progress. To stop outbound catalog events, set **SOCIAL_PUBLICATOR_WEBHOOK_ENABLED=false** in Dubai Shop. These switches do not delete pending outbox, inbox, selection, or trigger records.

Never put API tokens, webhook secrets, bot tokens, or customer data in Git. Use deployment secrets, HTTPS, queue monitoring, and log review. A webhook HTTP 202 is an inbox acknowledgement, not a successful social post.
