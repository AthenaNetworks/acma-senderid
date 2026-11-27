# Changelog

## 1.0.1 - 2026-11-28

### Fixed

- A 2xx response whose body is not JSON now throws `UnexpectedResponseException`, which
  extends `ServerException`, instead of a plain `ApiException`. The request may have been
  applied, so it must not be treated as a refusal and blindly repeated.
- `brands()->create()`, `senderIds()->create()` and `partners()->create()` throw
  `UnexpectedResponseException` when ACMA's success response has no ID, instead of
  returning an empty string.
- A `ChatbotInfo` read from a response re-sends `x-ACMA-expiresOn` as the same instant
  `expiresOn()` reports. Before, an offset on the response value (such as ACMA's
  misleading `Z`) was ignored when reading and honoured when sending, which shifted the
  expiry by the Sydney UTC offset.

## 1.0.0 - 2026-11-26

- Initial release: ACMA SMS Sender ID Register API client (brands, chatbots/Sender IDs,
  partners, push notification subscription, network providers, valid use case check).
- OAuth 2.0 client credentials with in-memory and PSR-16 token caching.
- Typed request/response DTOs with spec-derived validation and typed exceptions.
- Webhook token issuer and notification parser.
- Laravel service provider, facade, webhook routes, events and Artisan commands.
- Handles ACMA's undocumented `x-ACMA-expiresOn` behaviour: 7-digit fractional format,
  Australia/Sydney local time in responses, and re-sending the expiry on chatbot updates.
- Notification parsing for ACMA's actual payload (`Entity.EntityType/EntityId/NotifyReason`),
  `NotifyReason` enum, and `SenderIdClient::fetchNotified()`.
- Live write tests (brand, draft Sender ID, telco partner lifecycles) for test tenants.
