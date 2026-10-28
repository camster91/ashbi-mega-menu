## Change

Describe the concrete problem and the resulting behavior. Link the issue (`Refs #` or `Closes #`).

## Testing

- [ ] Built block and icons from source (`npm run build:brand && npm run build:icons && npm run build`)
- [ ] `npm test` and `npm run test:php` pass
- [ ] Added or updated tests for the change, or explained below why not
- [ ] Browser checks (`npm run test:e2e`) for editor or public-menu changes
- [ ] Checked keyboard, screen-reader names/states and the phone drawer when the UI changed

List the checks actually run, with browser and WordPress versions, and anything not verified.

## Screenshots

Before and after for visible changes, at desktop and phone widths. Remove client names and private URLs.

## Compatibility

- [ ] Existing saved menus load and render unchanged (no data format, field name or nonce changes), or the migration is described below
- [ ] Works on the minimum supported WordPress (6.6) and PHP (8.2)
- [ ] Theme, page-builder or cache behavior changes are noted and `docs/compatibility.md` is updated if a claim changes

## Privacy and security

- [ ] No credentials, personal data or private client content in code, fixtures, screenshots or logs
- [ ] No new remote requests, telemetry, tracking or account requirements
- [ ] New input is sanitized, output is escaped, and new AJAX/REST endpoints check a nonce and capability

## Rollback

How can a site undo this if it causes a problem (downgrade to the previous ZIP, a setting, a filter)? Does downgrading keep saved menus readable?
