# World Politics — environment and Stripe integration prerequisites

## Environment

Canonical template: [games/world-politics/.env.example](../games/world-politics/.env.example).
It is included by the exact-commit source packaging script. Real secrets must
never enter Git, CI logs, chat, or the public document root. Existing ignore
rules exclude .env and .env.* except .env.example.

PHP currently reads the process environment via getenv(); it does not
automatically parse .env files. For local shell-only testing, copy the template
to a private file (chmod 600), edit it locally, and load it using
`set -a; . /absolute/private/path/wp.env; set +a`.
For production use separate service environments or a secrets manager.
Do not inject operator flags, Stripe keys or dataset reader credentials into
unrelated public workers. Game and Research use independent MariaDB schemas,
users and evidence keys; never reuse the evidence keys. Generate each secret
independently using `openssl rand -hex 32`. Preserve evidence keys for
historical signature verification. Use HTTPS, explicit trusted proxy
configuration, and separate least-privilege installer/operator/runtime users.

Web document roots: games/world-politics/public and
games/world-politics/research/public. Never serve the repository root.
Operator flags default to NO and must only be enabled for the appropriate CLI
invocation. No actual secret, production URL or pricing value is supplied.

## Stripe — selected provider, integration not yet implemented

The reserved variables are a proposed config contract, not active runtime
features. Start in Stripe test mode (no real payment). Use hosted Checkout for
one-off credit packs and recurring subscriptions, and Customer Portal for
payment method and subscription management.

Commercial decisions still needed:
- Game Premium monthly EUR price.
- Research Standard and Pro monthly EUR prices and included credits.
- PAYG credit pack quantity and EUR price for each Research tier; whether both
  use the same credit conversion or different prices.
- Tax treatment, invoicing entity and permitted countries; whether prices are
  displayed tax inclusive or exclusive.
- Subscription upgrade/downgrade, cancellation, unused monthly allowances,
  optional prepaid overage, refunds, disputes and chargeback policies.

Operator setup after code is ready:
- Stripe test account; create recurring monthly prices and one-time pack prices,
  copy the non-secret price IDs into local configuration.
- Configure server-only test API credentials and the endpoint signing secret
  locally (never disclose real keys in chat or Git). Pin a supported Stripe
  API/webhook version after integration verification.
- Configure fixed HTTPS return URLs and a webhook endpoint after deployment.
- Verify signatures on the raw request body, reject wrong mode, reconcile
  payment/price/currency and customer mapping on the server. Handle duplicate
  and out-of-order events. Renew from paid invoice service periods, credit
  one-time packs only after confirmed payment, reconcile refunds/disputes
  and cancellation without trusting the browser redirect.
- Confirm idempotent processing and separation of game and Research stores
  with test-mode events before any live-mode activation.

Official references:
https://docs.stripe.com/checkout/fulfillment
https://docs.stripe.com/billing/subscriptions/webhooks
https://docs.stripe.com/webhooks/signature
https://docs.stripe.com/customer-management

The current billing engine already supports operator-recorded funding and
entitlements. No Stripe checkout, webhook receiver, payment reconciliation,
production tax configuration or payment transaction has been performed in
this configuration-only increment.

## Implemented Research test-mode increment
Set WP_STRIPE_ENABLED=YES and the exact API/webhook version 2024-10-28.acacia, test credentials, configured EUR card-only prices/credit quantities and HTTPS redirects. Operator creates a Checkout session for an existing tenant with `php games/world-politics/research/operator.php stripe-checkout TENANT standard-monthly UNIQUE_REQUEST_KEY` (offers standard-monthly, pro-monthly, standard-credits, pro-credits). Public webhook at `/research/v1/stripe/webhook` validates raw-body signature, five-minute timestamp, test mode and API edition. It retrieves Checkout/Invoice, line, PaymentIntent and Charge via Stripe, confirms the fixed price and paid state, and transactionally records the payment ID before returning success. Initial and recurring paid monthly invoices can renew entitlements; stale periods do not reset credits. The existing tenant expiry is still required for use of prepaid credits. One subscription order per tenant; cancellation, replacement, refund/dispute reversal, tax, promotions, Customer Portal, self-service onboarding/checkout, Game Premium payment and real Stripe sandbox acceptance remain pending. Live keys are rejected by code. No actual Stripe request was made in this increment. CI tests inject a deterministic fake transport.

## Complimentary operator accounts
Research operator CLI `create-admin standard monthly` (or standard payg, pro monthly, pro payg) creates a dedicated non-billable identity without ordinary client keys. Configure its tenant ID and an independent bearer SHA-256 in the corresponding WP_ADMIN_RESEARCH_* variables, then enable WP_ADMIN_RESEARCH_ENABLED=YES. Generate bearer locally using openssl rand -hex 32; calculate digest with printf %s "$TOKEN" | sha256sum. Store bearer in a password manager; never log it. Use normal Authorization: Bearer. Each profile retains its own permissions and quotas; waived units are audited separately without fake payment transactions, and shared hard limits remain enforced. Disable flag or rotate digest to revoke. No cross-tenant impersonation. Game operator uses a regular dedicated game account and session, configured with WP_ADMIN_GAME_ACCOUNT and WP_ADMIN_GAME_ENABLED=YES; it can issue its own Premium data token without payment, subject to existing ownership checks. Existing databases need reviewed additive admin_exempt/waived_units columns; defaults are off. No full universal master token is provided by design.

## Explicit environment isolation and lifecycle
WP_APP_ENV=development|staging|production. Test Stripe only accepts development/staging and a matching immutable-by-normal-operation database marker (`stripe-bind-environment` operator CLI on a dedicated nonproduction schema; never run against a production schema). Live remains intentionally blocked. Operator `stripe-portal TENANT` creates a hosted portal for an already bound subscription customer; disable plan changes in Stripe Portal configuration until upgrade/proration logic is implemented. Confirmed canceled/unpaid subscriptions lose monthly entitlement; confirmed refunds (including partial) or disputes on settled payments freeze the tenant for manual reconciliation, preserving ledger balances and recording a hold. This is not automated credit reversal, dispute resolution or tax handling. No actual Stripe sandbox calls performed. Game Premium remains pending.
