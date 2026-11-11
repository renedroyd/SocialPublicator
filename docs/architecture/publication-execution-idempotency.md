# Publication execution: atomic claims and uncertain outcomes

SocialPublicator is the system of record for each publication occurrence and target.

## Claim contract

`POST /api/bot/executions/start` returns:

- `data`: the persisted execution.
- `claimed`: true only when this request atomically moved a `pending` or `failed`
  execution to `running`.

The row is locked in a database transaction. A request that encounters an existing
`running`, `unknown`, or `succeeded` execution receives `claimed: false`. The
agent must never publish unless it receives `claimed: true`.

## Execution states

| State | Meaning | Automatic claim |
|---|---|---|
| pending | Waiting for an agent | Yes |
| running | Exclusively claimed / delivery in progress | No |
| succeeded | Delivery confirmed | No |
| failed | Confirmed failure before an uncertain external outcome | Yes |
| unknown | External delivery may have happened; reconciliation required | No |

The existing status column already supports string values, so the `unknown` state
requires no schema migration.

## Uncertain delivery

The agent reports `outcome_unknown=true` when the external platform may have accepted
the post but confirmation was lost. The API transitions `running` to `unknown`.
An operator or a later reconciliation process may resolve the record to `succeeded`
or, when there is reliable evidence that no post occurred, a controlled retry path.

Do not automatically convert stale `running` or `unknown` executions to `failed`.
A timeout or process crash does not prove that the external platform rejected the post.
