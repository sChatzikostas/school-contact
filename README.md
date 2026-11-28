# SchoolContact

![SchoolContact](school-contact/assets/logo-horizontal.svg)

Connecting schools and families. [Ελληνικά](README.el.md)

Repository: <https://github.com/sChatzikostas/school-contact>

Installation packages for published SchoolContact releases are available from the [GitHub Releases page](https://github.com/sChatzikostas/school-contact/releases).

A small WordPress plugin for parent/guardian communication with school
administration and school-unit staff. The public interface and administration screens
are in Greek. The plugin is deliberately self-contained: it uses WordPress APIs,
native PHP, HTML, CSS, and minimal vanilla JavaScript.

## Features

- A responsive `[school_communication]` contact form with grouped native recipient selection and recipient guidance.
- Administration for school-unit details, an optional principal/director name, and available staff recipients.
- Staff search, filtering, sorting, advisory duplicate warnings, and protection against closing an edited staff form accidentally.
- Staff recipients may have an official email, an approved alternative email, or both; they are publicly selectable only when available and they have at least one valid address.
- Secure server-side recipient resolution; recipient email addresses are never put in public markup.
- Plain-text UTF-8 WordPress email with school-unit identity, a clear reply instruction, and the parent email as Reply-To.
- Greek server-side validation and JavaScript-enhanced inline validation, submission progress, success feedback, and eligible failure recovery.
- Privacy guidance, honeypot, nonce, signed timing token, duplicate-submission claim, and pseudonymized-IP rate limits.

## Requirements

Tested with WordPress 6.8.3 and PHP 8.3. A working WordPress mail transport is
required for delivery. The plugin has no Composer, npm, framework, or custom
database-table dependency.

## Release availability

When an authorized administrator opens **Help & Information**, SchoolContact may
check GitHub for a newer stable release with an uploaded installation package. A
verified result is cached for 24 hours; an unavailable check is cached for one
hour. Installation remains manual through the [GitHub Releases page](https://github.com/sChatzikostas/school-contact/releases).

The request uses the server's network IP address and generic HTTP request
metadata. School, staff, parent, site, and contact information are not
intentionally transmitted. Network restrictions or GitHub API availability can
prevent the check without affecting the plugin's core functionality.

## Installation

1. Download the installation ZIP and upload it through **Plugins - Add New - Upload Plugin**, or copy `school-contact/` into `wp-content/plugins/school-contact`.
2. Activate **SchoolContact** from **Plugins**.
3. Open **Επικοινωνία με το σχολείο** in the WordPress administration menu.
4. Under **Γενικές ρυθμίσεις**, configure the school-unit display name, optional principal/director name, and school-unit administration email address.
5. Under **Προσωπικό**, add recipients and enable only recipients with at least one valid email address.
6. Add `[school_communication]` to a normal WordPress page.

After publication, send a controlled test message and verify receipt through the
configured mail system.

## Updating

Before updating, create a backup and use a published installation ZIP through
the normal WordPress plugin update or replacement workflow. Then confirm the
configuration and send a controlled test message through the configured mail
system.

The public privacy wording is provisional and should be approved by the school
unit before publication. Alternative staff addresses require school-unit approval,
and the school unit must provide appropriate privacy information. If WordPress has
a privacy-policy page configured, the form links to it automatically.

## Email routing

Messages addressed directly to the school-unit administration go only to its
configured email address. Messages addressed to staff are sent to every configured
valid staff destination: the official address, the approved alternative address,
or both when both are present. Staff messages are copied to the school-unit
administration address unless it is already a destination, using case-insensitive
deduplication. Recipients can therefore be visible to one another in email
headers. The plugin does not use BCC, automatic retries, or parent acknowledgements.
The parent address is supplied as Reply-To so recipients can reply directly.

## Privacy and security

Submitted messages and parent details are not stored by the plugin. They may remain
in receiving mail systems according to those systems' own retention practices.
Recipient addresses are resolved only on the server. The form applies server-side
validation, header-injection checks, a WordPress nonce, a honeypot, a signed
minimum two-second completion time, and a single-use submission claim.

Rate limits are three attempts per five minutes and ten per hour. They use HMAC-
derived identifiers rather than raw IP addresses. These transient counters are
intentionally non-atomic across simultaneous requests, so they reduce routine
abuse but are not complete bot protection. No CAPTCHA, tracking cookie, or
third-party service is used.

Use a cache configuration compatible with dynamic nonces and signed tokens, or
exclude pages containing `[school_communication]` from full-page caching. Cached
form HTML can contain stale tokens. Response headers reduce caching risk for
recovered submission responses, but cannot control third-party caches that ignore
them.

## Known limitations

- A successful form submission confirms that WordPress accepted the message for its configured mail transport; it does not guarantee final delivery.
- Inline validation, duplicate warnings, unsaved-change protection, submission progress, and selected recovery improvements use JavaScript. Without JavaScript, the native form and server validation remain available; some rejected submissions cannot retain values. Expired forms and submissions rejected for security reasons may require the user to enter values again.
- Transient-backed rate limits are intentionally non-atomic across simultaneous requests.
- Theme and accessibility checks are representative, not a guarantee of universal theme compatibility or complete WCAG conformance.

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
