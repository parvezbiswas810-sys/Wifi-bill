# Premium ISP WiFi Billing & MikroTik Management System

A production-ready ISP billing platform: real MikroTik RouterOS integration, per-customer
billing schedules, invoice generation, payment submission and approval, and automatic
internet suspension and restoration.

Built with Laravel 13, Blade, Tailwind CSS 4 and Alpine.js. White and light-blue theme
with a dark mode toggle.

## What this system does

- ISP administrators sign in at `/loginisp` and manage only their own data.
- Customers sign in at `/` and see only their own invoices, payments and connection.
- PPP secrets and live sessions are imported from real MikroTik routers over the
  RouterOS API.
- Each customer is invoiced on their own schedule. Overdue customers are suspended at
  the configured time; approving a payment restores them.
- Pending payment submissions never count as collected money.

## Requirements

| Component | Version |
|-----------|---------|
| PHP | 8.3 or newer (8.4 recommended) |
| Extensions | pdo_mysql, mbstring, openssl, curl, json, bcmath, ctype, fileinfo, tokenizer, xml |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Node.js | 20+ (build time only) |
| MikroTik | RouterOS 6 or 7, API or API-SSL reachable from the server |

## Local setup

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# set DB_* in .env, then:
php artisan migrate --seed
php artisan serve
```

Set `SEED_ADMIN_PASSWORD` in `.env` before seeding, otherwise a random password is
printed once.

## Scheduled jobs

Billing is driven by Laravel's scheduler. One cron entry runs everything:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

Registered jobs:

| Command | Frequency | Purpose |
|---------|-----------|---------|
| `billing:generate-invoices` | every minute | Create invoices whose billing time has arrived |
| `billing:suspend-due` | every minute | Suspend overdue customers at the configured time |
| `billing:mark-overdue` | daily 07:00 | Flag unpaid invoices past their due date |
| `mikrotik:sync` | every 5 minutes | Import PPP secrets and live sessions |

Every command is idempotent and safe to run repeatedly. Invoice generation is guarded by
a unique index on `(customer_id, period_key)`, so a customer is never invoiced twice for
the same billing period.

## Key routes

| Route | Access |
|-------|--------|
| `/` | Customer login |
| `/loginisp` | ISP administrator login |
| `/admin/dashboard` | Admin dashboard |
| `/admin/customers`, `/admin/customers/create` | Customer billing management |
| `/admin/ppp-clients` | MikroTik PPP clients (active / inactive / all) |
| `/admin/billing-history` | Invoices and billing history |
| `/admin/payments` | Pending payment approvals |
| `/admin/payment-methods` | bKash, Nagad, Rocket, bank, cash |
| `/admin/reports` | Ten reports with CSV export and print |
| `/admin/settings` | ISP profile, billing policy, security, audit log |
| `/admin/mikrotik` | Router configuration and synchronization |
| `/customer/dashboard` | Customer dashboard |
| `/customer/invoices` | Customer invoices |
| `/customer/payments` | Submit and track payments |
| `/customer/connection` | Connection status |
| `/customer/profile` | Account details |

## Money and status rules

These rules are enforced in code and covered by tests:

- **Collected** means approved payments only. A pending submission does not change any
  collected total.
- **Approving a payment** records exactly one payment row. Repeated clicks are rejected,
  and the `payments.submission_id` unique index is the final backstop.
- **Partial payments** leave the correct outstanding balance and mark the invoice
  `partial`.
- **Suspension** only happens when an invoice is genuinely overdue and the router
  confirms the change. A failed router operation never marks a customer suspended.
- **Restoration** only happens when the billing system owns the suspension
  (`suspension_owned_by_billing`). An account disabled manually on the router for another
  reason is never enabled by billing.
- **Connectivity** is reported from live PPP sessions, not from the PPP secret being
  enabled. A customer is not "online" just because their secret exists.

## MikroTik preparation

On the router:

```
/ip service enable api
# or, for TLS on port 8729:
/ip service enable api-ssl

/user add name=billing password=STRONG_PASSWORD group=full
# then narrow it: create a group with api, read, write, test policies
```

Also allow the hosting server's IP through any firewall or address list, and restrict the
API source address to that IP.

Credentials are encrypted at rest with the application key and are never rendered into
HTML, JavaScript, logs or API responses.

## Testing

```bash
php artisan test
```

Covers tenant isolation, invoice idempotency, payment approval idempotency, partial
payments, duplicate references, suspension gating, restoration ownership, and the RouterOS
wire protocol.

There is also a page-level smoke test that renders every route against seeded demo data:

```bash
php artisan app:smoke-test --fresh
```

It refuses to run when `APP_ENV=production`.

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for the cPanel walkthrough, and
[deploy/install.php](deploy/install.php) for a one-shot web installer.

## Security notes

- Separate authentication guards for administrators and customers.
- Every ISP-owned model is scoped to the authenticated ISP by a global scope, and record
  lookups return 404 rather than leaking another tenant's data.
- Login throttling, CSRF protection, request validation and output escaping throughout.
- Router credentials encrypted at rest; payment screenshots stored outside the public root.
- Financial changes are written to an audit log with the responsible actor.
