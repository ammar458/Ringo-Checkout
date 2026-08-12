# Smart Add-on Module

## New listing flow

1. Choose a listing package.
2. Choose optional add-ons.
3. The Add Boat form updates its photo and video limits.
4. Submit the listing and pay for the package plus selected add-ons.

Package base limits:

- Standard: 4 gallery photos, 0 video URL fields
- Featured: 10 gallery photos, 1 video URL field
- VIP: 25 gallery photos, 2 video URL fields
- Pro: 25 gallery photos, 2 video URL fields

A Gallery Photos form add-on adds its configured amount to the photo limit. A Video URL Fields form add-on adds its configured amount to the video allowance.

## Add-on types

- Form add-on: changes gallery-photo or video-field limits.
- System add-on: changes price and records the service without changing the form.

Manage these under Ringo Custom Checkout > Add-ons.

## Published boat purchases

Use `[ringo_addon_services]` on the services page. Signed-in sellers can select one of their published boats, choose add-ons, and pay only for those add-ons. The listing package is not charged again. Completed purchases appear under Ringo Custom Checkout > Add-on Orders.

## Services page purchase choice

The `[ringo_addon_services]` page now uses one radio choice instead of two permanent sections. Customers choose **New listing** or **Existing published boat**, and only the matching checkout flow is shown.

## Existing published boat flow

After a successful add-on-only Stripe or PayPal payment, the customer is redirected to
`/account/edit-post/?_post_id=BOAT_ID`. The purchased add-ons are saved to the boat before
the redirect, so gallery and video field limits update immediately on the native edit form.

## Version 9.3 video-field compatibility fix

- Extra Video is recognized as a form add-on in both Add Boat and Edit Boat forms.
- Legacy add-on rows named Extra Video or Additional Video are repaired at runtime even when older saved settings contain `system`, `none`, or a zero effect amount.
- The frontend also detects legacy video add-ons immediately when the customer selects them.
- Standard gains its first video URL field, Featured gains a second, and VIP/Pro gain a third when one Extra Video add-on is active.
