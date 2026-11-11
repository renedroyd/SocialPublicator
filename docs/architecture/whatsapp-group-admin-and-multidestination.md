# WhatsApp group administration and multidestination contract

WBot synchronizes WhatsApp groups for the configured connected account. It includes
`is_admin` only when the connected account's identity can be matched to the group's
participant metadata. The check supports WID and LID identifiers. Missing metadata is
not interpreted as permission and does not erase a previously verified value.

SocialPublicator stores the verified result in `whatsapp_groups.is_admin` and the
timestamp in `admin_checked_at`. The publication form filters WhatsApp destinations
to `is_admin=true`, then applies the existing workspace membership, account, and
destination permissions. WhatsApp administrator status is necessary but not sufficient:
the workspace user must still have publication permission.

The bot publication API returns all selected and authorized WhatsApp and Facebook
groups. WBot processes each group as an independent target and obtains a separate
atomic execution claim for each occurrence/target pair. WhatsApp remains sequential;
Facebook Groups retain the bounded concurrent browser-session queue.

## Three-application boundary

- Dubai-Shop remains the optional commercial source for product catalog, variants,
  prices, and stock.
- SocialPublicator owns campaigns, scheduling, publication records, social accounts,
  group selection, workspace permissions, and execution state.
- WBot owns the local browser sessions and the delivery adapters: WPPConnect for
  WhatsApp and the persistent browser session for Facebook Groups. Facebook Pages
  remain on the official Meta OAuth/API path in SocialPublicator.
