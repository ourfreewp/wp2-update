# Regression Checklist

## Webhook Validation
- [ ] Verify webhook signature validation works with multiple app secrets.
- [ ] Ensure no double decryption occurs for webhook secrets.
- [ ] Test webhook validation with invalid signatures and app IDs.

## REST Nonce Enforcement
- [ ] Confirm `wpApiSettings` is localized to `wp-api-fetch` with `wp_rest` nonce.
- [ ] Test all REST endpoints for proper nonce validation.
- [ ] Verify permission callbacks enforce both capability and nonce checks.

## Bootstrapping and Autoloader
- [ ] Ensure `Init::boot` is only registered once.
- [ ] Verify no duplicate autoloader inclusions in `wp2-update.php`.

## Cache API
- [ ] Test cache set, get, and delete operations for consistency.
- [ ] Verify cache invalidation works correctly with the object cache API.
- [ ] Check for any lingering transient API usage.

## General
- [ ] Run all unit and integration tests to confirm no regressions.
- [ ] Validate plugin functionality in a local WordPress instance.
