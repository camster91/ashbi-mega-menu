# Security policy

## Reporting a vulnerability

Please do not post exploitable vulnerabilities, exploit code or private data in public issues, pull requests or the WordPress.org forum.

Report privately through [GitHub's private vulnerability reporting](https://github.com/camster91/ashbi-mega-menu/security/advisories/new). If that is unavailable to you, open a public issue that only asks for a private channel, without any details.

Include:

- Affected plugin versions and WordPress/PHP versions.
- The role or access an attacker needs (logged out, subscriber, editor, administrator).
- Steps to reproduce and a minimal proof of concept.
- The impact (what an attacker can read, change or run).

## What happens next

1. **Acknowledge** the report, normally within 7 days.
2. **Assess** it: confirm the issue, rate severity (critical, high, medium, low; CVSS where useful) and decide the affected versions. You will hear whether it was accepted.
3. **Fix** it privately in a security advisory, with a regression test, then follow [docs/release-checklist.md](docs/release-checklist.md). Critical and high issues are released as soon as a verified fix is ready; medium and low issues go into the next scheduled release.
4. **Disclose**: publish the GitHub security advisory and release notes when the fixed version is available (on WordPress.org too once listed). Reporters are credited unless they prefer not to be. Please keep details private until then, or for 90 days after the report if no fix has been released, whichever comes first.

## Supported versions

Only the latest release receives security fixes. There is no long-term support branch. These are good-faith targets for a volunteer-maintained GPL project, not a service-level agreement.

## Scope

In scope: the plugin's PHP, JavaScript, REST and AJAX endpoints, import/export, uninstall and the built release ZIP. Out of scope: WordPress core, themes, other plugins, and issues that need an administrator to attack their own site (administrators can already run code), unless the plugin makes them worse.
