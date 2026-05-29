# NoFraud Connect (M2)

Integrates NoFraud's post-payment-gateway API functionality into Magento 2.

> **Technical Documentation:** For architecture diagrams, API layer details, class reference, database schema, cron jobs, payment method integrations, and full configuration reference, see **[docs/technical.md](docs/technical.md)**.

## Installation

Using Composer (recommended):

```sh
composer require nofraud/connect
bin/magento module:enable NoFraud_Connect
bin/magento setup:upgrade
```

For production environments, also re-deploy static content and run the DI compiler:

```sh
bin/magento setup:static-content:deploy
bin/magento setup:di:compile
```

## Configuration

Navigate to **Stores > Configuration > NoFraud > Connect** in the Magento admin panel.

Key settings:

| Setting | Description |
|---------|-------------|
| **Enabled** | Master toggle for the module |
| **Direct API Token** | Your NoFraud API token (encrypted at rest) |
| **Checkout Mode** | Environment selection: Production, Sandbox, Dev1, Dev2 |
| **Screened Order Status** | Limit screening to specific order statuses |
| **Screened Payment Methods** | Limit screening to specific payment methods (all if empty) |
| **Auto-cancel** | Automatically cancel orders that fail fraud screening |
| **Refund Online** | Attempt online refund when auto-cancelling |
| **Capture Authorization On Pass** | Auto-capture payment when order passes screening |

Order status mapping (pass/review/fail/error), customer group skip lists, review email notifications, and cron frequency are also configurable. See [Configuration Reference](docs/technical.md#configuration-reference) for the full list.

## Troubleshooting

Log files:

| File | Content |
|------|---------|
| `var/log/nofraud_connect/info.log` | Transaction results, API errors, cancellation events |
| `var/log/nofraud_connect/log-*.log` | Debug-level detail (when debug mode is enabled) |

Enable debug logging at **Stores > Configuration > NoFraud > Connect > Advanced NoFraud Connect Settings > Debug**.

## Testing

The module includes a PHPUnit test suite focused on security invariants (token handling, logging safety).

**Inside a Magento installation** (where `vendor/` is populated):

```sh
vendor/bin/phpunit --configuration phpunit.xml
```

**Standalone** (outside a Magento project): download the PHPUnit PHAR since `composer install` cannot resolve `magento/framework` in isolation — the module is designed to be installed within a Magento project's dependency tree.

```sh
curl -sSL https://phar.phpunit.de/phpunit-10.phar -o phpunit.phar
php phpunit.phar --configuration phpunit.xml
```

The standalone setup uses minimal Magento class stubs in `Test/Stubs/` so PHPUnit can mock framework types without the full Magento installation.

## Support

- Email: support@nofraud.com
- General: info@nofraud.com
