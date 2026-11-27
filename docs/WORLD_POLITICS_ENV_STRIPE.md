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
