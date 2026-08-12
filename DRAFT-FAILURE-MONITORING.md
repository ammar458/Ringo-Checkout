# Draft Payment Failure Monitoring (v6.3)

## Covered conditions

The dedicated draft notification can now be triggered by:

- card rejection or Stripe confirmation failure
- Stripe/PayPal setup, order, intent, or capture failure
- payment interface stuck for more than two minutes
- delayed or pending gateway status
- browser-side or server-side gateway timeout
- gateway unavailable or no response
- checkout closed, refreshed, cancelled, or abandoned before completion
- incomplete payment returned by the gateway

Every event is added to `_ringo_draft_failure_history` and written to the Ringo log as `Draft payment failure condition fired` with the condition, attempt ID, provider, stage, source, and available gateway context.

## Separate email channel

The draft failure notification has its own per-attempt sent flags. It does not set the abandoned/unpaid follow-up flags and does not mark a boat `followed_up`. The existing follow-up jobs remain independent.

## Recipients

Default recipients are:

1. the seller email stored on the boat
2. the WordPress site `admin_email`

There was no hardcoded Josh email in the supplied plugin. If Josh is the WordPress site administrator, he already receives the admin copy. To add a separate Josh address, define this in `wp-config.php`:

```php
define( 'RINGO_DRAFT_FAILURE_JOSH_EMAIL', 'JOSH_EMAIL_HERE' );
```

Recipient behavior can also be changed with the `ringo_draft_failure_recipients` filter.

## Validation performed

- PHP syntax checked across every plugin PHP file except vendor dependencies
- extracted inline checkout JavaScript checked with Node.js
- payment success clears the active failure marker but retains failure history for tracing


## v6.3 email cleanup

Draft failure emails now show plain-language information only. Machine condition codes, raw diagnostic context, payment attempt identifiers, and raw draft URLs remain available in logs but are no longer displayed in email content.
