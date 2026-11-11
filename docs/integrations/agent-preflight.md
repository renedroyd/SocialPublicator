# Agent startup preflight

Every integrated WBot instance performs a read-only preflight before opening WhatsApp or Facebook browser sessions.

## Endpoint

`GET /api/bot/agent/health?instance_id={BOT_INSTANCE_ID}`

The endpoint is protected by the same bearer-token authentication and workspace resolution as the bot API. A scoped token receives only the social accounts explicitly assigned to its BotAgent record. The legacy token receives only accounts in its configured legacy workspace and is marked `legacy_workspace`.

The response includes the workspace and instance identifiers, authorization mode, authorized account IDs, account platform/type/status, and non-secret connection state. It never returns OAuth credentials, WPPConnect tokens, browser cookies, session references, local paths, or provider connection identifiers.

## WBot startup behavior

When `SOCIAL_ACCOUNT_ID` or `FACEBOOK_SOCIAL_ACCOUNT_ID` is configured, WBot calls the preflight before opening Chromium or WPPConnect. It stops startup when:

- The token cannot authenticate or the instance identity does not match.
- A configured account is not visible to the token.
- The account ID is invalid or its platform/type does not match the configured channel.
- An active/pending WPPConnect or browser session is already assigned to a different agent instance.

A pending account with no connection record is valid and can proceed to QR/browser pairing. A standalone WBot without either SocialPublicator account ID skips the preflight and retains its existing independent behavior.

## Safe rollout

1. Create the social account in the intended workspace.
2. Create a BotAgent and grant only the account IDs needed by that instance.
3. Issue a one-time agent token and place it in that WBot instance's environment.
4. Configure `BOT_INSTANCE_ID`, `SOCIAL_ACCOUNT_ID` and, when applicable, `FACEBOOK_SOCIAL_ACCOUNT_ID`.
5. Start WBot and verify the preflight log reports the expected workspace, instance, authorization mode and account statuses.
6. Only then pair a new session or resume an existing one.

The preflight is read-only. It does not create, migrate, rename, delete, or reset WhatsApp or Facebook session directories. Existing default session names and paths remain unchanged.
