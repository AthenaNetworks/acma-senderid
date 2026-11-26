# Changelog

## 1.0.0 - Unreleased

- Initial release: ACMA SMS Sender ID Register API client (brands, chatbots/Sender IDs,
  partners, push notification subscription, network providers, valid use case check).
- OAuth 2.0 client credentials with in-memory and PSR-16 token caching.
- Typed request/response DTOs with spec-derived validation and typed exceptions.
- Webhook token issuer and notification parser.
- Laravel service provider, facade, webhook routes, events and Artisan commands.
- Handles ACMA's undocumented `x-ACMA-expiresOn` behaviour: 7-digit fractional format,
  Australia/Sydney local time in responses, and re-sending the expiry on chatbot updates.
- Live write tests (brand, draft Sender ID, telco partner lifecycles) for test tenants.
