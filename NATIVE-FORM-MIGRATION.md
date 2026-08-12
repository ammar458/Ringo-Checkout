# Native Boat Form Migration - Version 7.0

## What changed

Version 7.0 replaces the two JetFormBuilder payment forms with native WordPress forms inside the Ringo Checkout plugin:

- Former form 1204: full new boat submission
- Former form 37231: Pay Now for an existing Draft boat

The Stripe, PayPal, coupons, Draft failure monitoring, payment publishing, seller emails, Josh/admin emails, abandoned checkout jobs, and unpaid follow-up jobs remain in the same plugin.

## Automatic placement

The plugin automatically adds the native forms on:

- `/add-boat/`
- `/account/edit-post/?_post_id=BOAT_ID`

While JetFormBuilder remains active for staging tests, the plugin hides the old forms with IDs 1204 and 37231 when the matching native form is present.

Manual shortcodes are also available:

- `[ringo_boat_submission_form]`
- `[ringo_boat_pay_now]`

Auto-placement can be disabled in custom code with the `ringo_native_auto_inject_forms` filter.

## Existing data preserved

The native submission keeps the existing Boats post type and these taxonomy slugs:

- `boatlength`
- `boatcategories`
- `boat-ownership`
- `boat-status`
- `boat-year`
- `motor-make`
- `motor-year`
- `state`
- `boat-make`

The package category mapping from the former form is preserved:

- Standard: term 415
- Featured: term 416
- VIP: term 417
- Pro: term 418

Boat ownership continues to use term 4.

The form stores the existing post meta names, including seller contact details, price, boat model, motor model, engine hours, stock number, HIN, videos, featured image, and gallery IDs. It writes both `motor_hours` and `engine_hours` for compatibility.

## Important dependency

JetFormBuilder is no longer required after testing. JetEngine is still required because the Boats post type, Boat Detail meta box, and boat taxonomies are currently registered in JetEngine.

## Staging test order

1. Install and activate version 7.0 while JetFormBuilder is still active.
2. Open `/add-boat/` as a normal seller account.
3. Confirm only one visible boat submission form appears.
4. Submit a boat with a cover photo and gallery images.
5. Confirm a Draft boat is created with the correct author, meta, taxonomies, featured image, and gallery.
6. Complete one Stripe test payment and confirm the Draft publishes.
7. Complete one PayPal sandbox payment and confirm the Draft publishes.
8. Trigger one rejected or abandoned payment and confirm the seller and Josh/admin receive the plain-language Draft notification.
9. Open `/account/edit-post/?_post_id=BOAT_ID` for an unpaid Draft and test Pay Now.
10. Confirm another user's Draft cannot be paid from the same URL.
11. Deactivate JetFormBuilder on staging.
12. Repeat the new-listing and Pay Now tests.

Do not remove JetEngine during this migration.

## Login page

Logged-out visitors are directed to `/register-login/`. The current boat form URL is included in the `redirect_to` query parameter so the login page can return the user to the form after authentication. The URL can be changed with the `ringo_native_login_url` filter.


## Version 7.4 phone format

The seller phone field now auto-formats and validates as `xxx-xxx-xxxx`. The same normalized format is saved to the boat and user metadata.


## Version 7.5 publish compatibility fix

- Stores native gallery attachment IDs as a comma-separated string, matching the child theme save handler.
- Normalizes gallery arrays created by versions 7.0 through 7.4 before publishing an existing Draft.
- Catches publish-time PHP errors so a third-party save hook cannot produce a public critical-error screen.
- A successful Stripe PaymentIntent can be safely retried through the same thank-you URL after upgrading.


## Version 7.6 compatibility update

- Saves `new_post_id`, `inserted_post_id`, and `post_id` using the real WordPress boat ID.
- Saves `inserted_boats` as the boat permalink for legacy templates.
- Backfills these values once for boats created by native form versions 7.0 through 7.5.


## Version 7.7

- Adds a JetEngine Profile Builder-safe fallback for the native Pay Now form on `/account/edit-post/`.
- Keeps the existing boat ownership, Draft/payment-status, package, and amount checks.
- Prevents duplicate rendering when the normal WordPress content-loop injection succeeds.

## Version 7.11

- Removes the inline "Listing saved. Opening payment options..." notice.
- Keeps the compact Pay Now form silent while the payment chooser opens.
- Allows `[ringo_boat_pay_now]` to resolve the current Boat inside a JetEngine listing card on `/account/`.
- Loads the checkout and native-form assets on the account dashboard.
- Returns no error markup when a listing card has no valid unpaid Boat context.


## Version 8.0 native edit form

- Adds `[ringo_boat_edit_form]` for `/account/edit-post/?_post_id=...`.
- Replaces the JetForm edit workflow with a native form that preloads the boat post, seller meta, taxonomies, featured image, and gallery.
- Paid boats show **Update Listing**.
- Unpaid boats show **Pay Now** at the end of the form. The edit is saved first, then the existing Stripe/PayPal chooser opens.
- Existing gallery images can be kept or removed. New images can be added within the package limit.
- The separate `[ringo_boat_pay_now]` shortcode still works on account listing cards, but stays hidden on the native edit endpoint.


## Version 8.2 gallery selection fix

The fast new-boat request now reads the actual `gallery[]` file input. Selected gallery files are counted correctly, removed from the lightweight first request, and sent only through the background media request. This prevents the false “Please select at least one gallery image” error and preserves the fast payment chooser.


## Version 8.3 year dropdown order

- Sorts Boat Year and Motor Year from newest to oldest in both native forms.
- Places 2027 first when that taxonomy term exists.


## Version 8.4 add-form boat status

- Removed the Boat Status selector from the Add Boat form.
- New boat submissions no longer require or assign a `boat-status` term.
- Boat Status remains available on the Edit Boat form.
