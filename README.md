# Sendability SmartRoute for Mautic

**Version:** 1.0.0  
**Author:** Ziyad Shoeky — [Sendability.com](https://sendability.com)  
**License:** GPL-3.0-or-later  
**Requires:** Mautic 7.0+, PHP 8.1+

---

## What It Does

SmartRoute lets you send emails through **two different SMTP servers** from within a single Mautic installation — automatically, based on routing rules you define.

The most common use case is routing Microsoft family addresses (Hotmail, Outlook, Live, MSN) through a dedicated secondary MTA (e.g. KumoMTA) while all other addresses continue using your default SMTP (e.g. SparkPost). This lets you manage sender reputation separately per mailbox provider.

---

## Features

| Feature | Description |
|---|---|
| **Domain-based routing** | Route emails by recipient domain (e.g. all `@hotmail.com` via secondary) |
| **Custom field routing** | Route emails based on any Mautic contact custom field value |
| **Secondary From identity** | Override the From address and Return-Path when sending via secondary SMTP |
| **Percentage split** | Send only X% of matching emails to secondary — the rest go to default |
| **Header-leak protection** | Clears stale `X-Transport` headers on every send to prevent routing bleed-over |
| **Lazy transport init** | Secondary SMTP connection is only created when actually needed |
| **Admin UI** | Full configuration from Mautic's Settings → Configuration page |
| **Routing test command** | CLI command to verify config and routing decisions without sending a real email |
| **Detailed logging** | Every routing decision logged to Mautic's prod log files |

---

## Installation

### 1. Copy the plugin

```bash
cp -r SendabilitySmartRouteBundle /var/www/mautic/plugins/
```

### 2. Set correct ownership

```bash
chown -R www-data:www-data /var/www/mautic/plugins/SendabilitySmartRouteBundle
```

### 3. Clear cache and reload plugins

```bash
php bin/console cache:clear --env=prod
php bin/console mautic:plugins:reload
```

### 4. Configure

Go to **Mautic → Settings → Configuration → Sendability SmartRoute** and fill in your settings (see Configuration section below).

---

## Configuration

All settings are saved in Mautic's `config/local.php` under the `smartroute_*` keys. They can be set via the UI or added directly to `local.php` for bulk deployment.

### Settings reference

| Parameter | Type | Default | Description |
|---|---|---|---|
| `smartroute_enabled` | bool | `false` | Master on/off switch |
| `smartroute_secondary_dsn` | string | — | DSN for the secondary SMTP server |
| `smartroute_mode` | string | `domain` | Routing mode: `domain` or `custom_field` |
| `smartroute_domain_list` | string | — | Comma-separated domains to route to secondary |
| `smartroute_custom_field` | string | — | Contact field alias (custom field mode) |
| `smartroute_field_value` | string | — | Field value that triggers secondary routing |
| `smartroute_from_email` | string | — | From email to use on secondary sends |
| `smartroute_from_name` | string | — | From name to use on secondary sends |
| `smartroute_secondary_percentage` | int | `100` | % of matching emails routed to secondary (0–100) |

### Secondary SMTP DSN format

```
smtp://hostname:port?verify_peer=0&auto_tls=false&restart_threshold=1
```

The `restart_threshold=1` option is **strongly recommended** — it forces Symfony Mailer to open a fresh SMTP connection for every send, preventing stale-buffer desync errors (`354 vs 250`) that occur when connections are reused across batches.

Full example (KumoMTA on local network, no TLS):
```
smtp://10.0.0.1:2525?verify_peer=0&auto_tls=false&restart_threshold=1&restart_threshold_sleep=0
```

### Domain-based routing (recommended)

Set **Routing Mode** to `domain` and enter a comma-separated domain list:

```
hotmail.com, hotmail.fr, hotmail.co.uk, outlook.com, outlook.fr, live.com, live.fr, msn.com
```

All recipient addresses at these domains will be sent via the secondary SMTP. Everyone else uses the default.

A full Microsoft-family domain list covering all regional variants is available in `servers.txt` (used in production).

### Custom field routing

Set **Routing Mode** to `custom_field` and specify:
- **Field alias** — the alias of any Mautic contact field (e.g. `smtp_route`)
- **Field value** — the value that triggers secondary routing (e.g. `kumo`)

Any contact whose `smtp_route` field equals `kumo` will be sent via the secondary SMTP.

### Percentage split

Set **Secondary SMTP Percentage** to any value between `0` and `100`:

- `100` — all matching contacts go to secondary (default, current behaviour unchanged)
- `50` — half go to secondary, half go to default
- `20` — 20% go to secondary, 80% go to default
- `0` — nothing goes to secondary (effectively disables routing without turning off the plugin)

The split is **deterministic per contact** — the same contact always hits the same transport on every send within a campaign, rather than flipping randomly each time.

---

## How It Works (Technical)

```
Mautic sends email
      │
      ▼
EMAIL_PRE_SEND event fires
      │
      ▼
SmartRouteSubscriber::onEmailPreSend()
      │
      ├─ Clears any stale X-Transport header on the shared message object
      │
      ├─ Calls RoutingResolver::shouldRouteToSecondary()
      │      ├─ Check enabled flag
      │      ├─ Check mode (domain / custom_field)
      │      ├─ Match recipient domain or contact field
      │      └─ Apply percentage gate (crc32 hash of email % 100)
      │
      ├─ [NO MATCH] → return, message goes to default transport
      │
      └─ [MATCH] → TransportInjector::ensureTransportRegistered()
                        │
                        └─ Registers secondary DSN under key "smartroute"
                           in Symfony's Transports container (via reflection)
                                 │
                                 ▼
                        Sets X-Transport: smartroute on message
                        Overrides From + Return-Path to secondary identity
                                 │
                                 ▼
                        Symfony Mailer reads X-Transport header
                        Routes to secondary transport
                        Strips header before sending
```

### Why the header-clearing guard matters

Symfony's `Transports::send()` **re-adds** `X-Transport` to the message object whenever the secondary transport throws an exception (e.g. a Kumo connection timeout). Mautic reuses a single message object across all recipients in a batch and only resets `To`/`Cc`/`Bcc` between sends — not the headers. Without the guard, a failed Kumo send on recipient A would leave a stale `X-Transport: smartroute` on the message, causing recipient B (a non-matching address) to also be routed to Kumo — with the wrong From identity (`crm` instead of `mta1`). The guard clears this on every invocation before the routing decision is made.

---

## Testing

Run the built-in CLI test to verify config, transport injection, and routing decisions without sending a real email:

```bash
php bin/console sendability:smartroute:test
```

Output example:
```
Routing Tests (via real RoutingResolver)
 ─────────────────────────────────────────────────────
  user@gmail.com    →  DEFAULT (main)
  user@hotmail.com  →  SECONDARY (smartroute / mta1)
  user@hotmail.fr   →  SECONDARY (smartroute / mta1)
  user@outlook.com  →  SECONDARY (smartroute / mta1)
  user@live.com     →  SECONDARY (smartroute / mta1)
  user@msn.com      →  SECONDARY (smartroute / mta1)
  user@free.fr      →  DEFAULT (main)
```

---

## Deploying to Multiple Mautic Servers

The plugin code lives in `plugins/SendabilitySmartRouteBundle/` — copy this folder to each server.

The configuration (domain list, DSN, From identity, percentage) lives in each server's `config/local.php`. Copy the `smartroute_*` block and adjust per-server values:

```php
// config/local.php
'smartroute_enabled'              => 1,
'smartroute_secondary_dsn'        => 'smtp://YOUR_KUMO_IP:2525?verify_peer=0&auto_tls=false&restart_threshold=1',
'smartroute_mode'                 => 'domain',
'smartroute_domain_list'          => 'hotmail.com,hotmail.fr,outlook.com,outlook.fr,live.com,live.fr,msn.com,...',
'smartroute_from_email'           => 'info@mta1.yourdomain.com',
'smartroute_from_name'            => 'Your Company',
'smartroute_secondary_percentage' => 100,
```

After copying, on each server:
```bash
php bin/console cache:clear --env=prod
php bin/console mautic:plugins:reload
php bin/console sendability:smartroute:test
```

---

## Logging

All routing decisions are written to Mautic's prod log (`var/logs/mautic_prod-YYYY-MM-DD.php` or `var/logs/prod-YYYY-MM-DD.php`):

```
[SendabilitySmartRoute] Secondary transport registered successfully.
[SendabilitySmartRoute] From address set to Comparatifdirect <info@mta1.yourdomain.com>.
[SendabilitySmartRoute] Routing email to user@hotmail.fr via transport "smartroute".
[SendabilitySmartRoute] Routing matched but secondary transport could not be registered.
```

---

## File Structure

```
SendabilitySmartRouteBundle/
├── Assets/img/                         Plugin icons
├── Command/
│   └── TestRoutingCommand.php          CLI: sendability:smartroute:test
├── Config/
│   ├── config.php                      Plugin metadata + parameter defaults
│   └── services.php                    Service container definitions
├── Controller/
│   └── AjaxController.php              AJAX endpoints (mode toggle)
├── DependencyInjection/
│   └── SendabilitySmartRouteExtension.php
├── EventSubscriber/
│   ├── SmartRouteSubscriber.php        Core: hooks EMAIL_PRE_SEND, applies routing
│   └── ConfigSubscriber.php           Registers config form section
├── Form/Type/
│   └── SmartRouteConfigType.php        Settings form fields
├── Resources/views/FormTheme/Config/
│   └── _config_smartrouteconfig_widget.html.twig   Settings UI template
├── Service/
│   ├── RoutingResolver.php             Routing decision logic + percentage gate
│   └── TransportInjector.php          Registers secondary DSN into Symfony Mailer
├── Translations/en_US/
│   └── messages.ini                    UI labels and tooltips
├── SendabilitySmartRouteBundle.php     Bundle entry point
├── composer.json
└── README.md
```

---

## Known Issues & Notes

- **`restart_threshold=1` is required in the secondary DSN** when using KumoMTA or any MTA that drops idle connections. Without it, Symfony Mailer reuses connections across batches and can read stale SMTP responses, causing `354 vs 250` desync errors that look like KumoMTA failures but are actually Mautic-side.
- The plugin uses PHP reflection to inject the secondary transport into Symfony's `Transports` container at runtime. This is the same technique used internally by Mautic's `MailHelper`. If a future Symfony upgrade changes the internal property names, the `TransportInjector` will need updating.
- The percentage split uses `crc32(email) % 100` — deterministic and fast, but not cryptographically uniform. For very small contact lists, the actual split may differ from the configured percentage; at scale (1000+ contacts) it converges to within ±5%.

---

## Changelog

### 1.0.0
- Initial release
- Domain-based and custom field routing modes
- Secondary From / Return-Path identity override
- Secondary SMTP percentage split (deterministic per contact)
- Header-leak guard for Symfony `Transports::send()` failure re-add behaviour
- Built-in CLI test command using real `RoutingResolver`
