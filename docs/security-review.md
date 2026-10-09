# Release security review

The release workflow runs the official WordPress Plugin Check action without global error/warning exclusions. Any inline PHPCS annotations describe a specific reviewed operation:

- Admin GET parameters choose a view, filter, page or escaped status notice. They do not write state, and the screen checks administrator capabilities. Requiring a mutation nonce for these read-only selections would not add a protection boundary.
- AJAX handlers explicitly verify `abmm_admin` nonces and then check `manage_options` before reading or writing menu data. Form handlers use `check_admin_referer` and capabilities.
- JSON menu data is size/depth checked, decoded, structurally validated and sanitized by menu field before persistence. Profile and branding arrays use their dedicated field sanitizers rather than flattening structured values into text.
- Upload imports check file errors, name/extension, size and `is_uploaded_file` before reading the temporary file. Content is validated as JSON and sanitized before persistence; no uploaded PHP is executed.
- Prepared MySQL GET_LOCK/RELEASE_LOCK queries serialize menu saves. Lock results must never be cached. An unavailable or busy lock returns an error rather than bypassing concurrency protection.
- Widget wrappers are passed through `wp_kses_post`; URLs, labels and attributes use context-specific escaping. Bundled SVG markup uses an explicit allowlist.

This review is scoped to the initial public distribution. It is not a guarantee against all vulnerabilities. Report concerns using SECURITY.md and verify client-specific theme/plugin combinations on staging.
