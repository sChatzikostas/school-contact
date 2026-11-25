# SchoolContact

![SchoolContact](school-contact/assets/logo-horizontal.svg)

Connecting schools and families. [Ελληνικά](README.el.md)

Repository: <https://github.com/sChatzikostas/school-contact>

The first stable release is being prepared. Follow the [GitHub Releases page](https://github.com/sChatzikostas/school-contact/releases) for published installation packages.

A small WordPress plugin for parent/guardian communication with school
administration and school staff. The public interface and administration screens
are in Greek. The plugin is deliberately self-contained: it uses WordPress APIs,
native PHP, HTML, CSS, and minimal vanilla JavaScript.

## Features

- A responsive `[school_communication]` contact form with grouped native recipient selection.
- Administration for school details, principal name, and available staff recipients.
- Staff recipients may have a υπηρεσιακό email, an approved alternative email, or both.
- Secure server-side recipient resolution; recipient email addresses are never put in public markup.
- Plain-text WordPress email with the sender email as Reply-To.
- Greek validation, accessibility support, privacy guidance, honeypot, nonce, signed timing token, duplicate-submission claim, and pseudonymized-IP rate limits.

## Requirements

Tested with WordPress 6.8.3 and PHP 8.3. A working WordPress mail transport is
required for delivery. The plugin has no Composer, npm, framework, or custom
database-table dependency.

## Installation

1. Download the installation ZIP and upload it through **Plugins - Add New - Upload Plugin**, or copy `school-contact/` into `wp-content/plugins/school-contact`.
2. Activate **SchoolContact** from **Plugins**.
3. Open **Επικοινωνία με το σχολείο** in the WordPress administration menu.
4. Under **Γενικές ρυθμίσεις**, configure the school display name, principal name, and the school administration email address.
5. Under **Προσωπικό**, add recipients and enable only recipients with at least one valid email address.
6. Add `[school_communication]` to a normal WordPress page.

The public privacy wording is provisional and should be approved by the school
before publication. If WordPress has a privacy-policy page configured, the form
links to it automatically.

## Email routing

Messages addressed to the school administration go only to the configured school
administration email address. Messages addressed to staff are sent to every
configured valid staff destination: the υπηρεσιακό address, the approved
alternative address, or both when both are present. Staff messages are copied to
the school administration address unless it is already a destination. The plugin
does not use BCC or automatic retries.

## Privacy and security

Submitted messages and parent details are not stored by the plugin. Recipient
addresses are resolved only on the server. The form applies server-side
validation, header-injection checks, a WordPress nonce, a honeypot, a signed
minimum two-second completion time, and a single-use submission claim.

Rate limits are three attempts per five minutes and ten per hour. They use HMAC-
derived identifiers rather than raw IP addresses. These transient counters are
intentionally non-atomic across simultaneous requests, so they reduce routine
abuse but are not complete bot protection. No CAPTCHA, tracking cookie, or
third-party service is used.

Exclude pages containing `[school_communication]` from full-page caching unless
a cache-compatible token mechanism is implemented. Cached form HTML can contain
stale signed tokens.

## Known limitations

- A successful form submission confirms that WordPress accepted the message for its configured mail transport; it does not guarantee final delivery.
- Transient-backed rate limits are intentionally non-atomic across simultaneous requests.
- Pages containing the form must be excluded from full-page caching unless a cache-compatible token mechanism is added.

## Local development

The included Docker Compose environment runs WordPress, MariaDB, and Mailpit for
local development only. MariaDB and Mailpit SMTP are internal-only; WordPress is
available at <http://localhost:8086> and Mailpit at <http://localhost:8026>.

```powershell
Copy-Item .env.example .env
docker compose up -d
```

The local `school-contact/` source is bind-mounted into WordPress. The development-only
MU plugin routes `wp_mail()` to Mailpit. WordPress uses the `local` environment
type and writes debug messages to `wp-content/debug.log` without displaying PHP
errors publicly.

Stop the environment with `docker compose down`. The named MariaDB volume keeps
local data. Never reuse local credentials outside development.

## Author and license

Design and development: Sotiris Chatzikostas

- Email: <mailto:s.chatzikostas@gmail.com>
- LinkedIn: <https://www.linkedin.com/in/sotiris-chatzikostas/>
- GitHub repository: <https://github.com/sChatzikostas/school-contact>

Licensed under GPL-2.0-or-later.
