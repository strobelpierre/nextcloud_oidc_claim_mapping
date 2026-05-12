<!--
  - SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Changelog

All notable changes to this project are documented in this file.

## [Unreleased]

### Added

- Initial scaffold from [oidc_groups_mapping v1.1.0](https://github.com/strobelpierre/nextcloud_oidc_groups_mapping/releases/tag/v1.1.0) (rule engine, claim resolver, Vue admin UI, REST API, OCC commands, CI matrix). Namespace renamed to `OCA\OidcClaimMapping`, app id `oidc_claim_mapping`.
- `mustache/mustache` declared in `composer.json` for the upcoming full Mustache template renderer (M4).
- `Service\TargetRegistry` — hardcoded list of 17 scalar user attributes accepted as rule targets (mirror of `user_oidc 7.4` `ProviderService::SETTING_MAPPING_*` constants). Exposes `isSupported()`, `isForbidden()`, `fromEventAttribute()`, `getSupportedTargets()`.
- `Model\Rule` — required `target` field plus optional `providerIdentifier` (defaults to `*`, the wildcard). New `appliesToProvider()` helper for the upcoming `iss`-based scoping (M3).
- `Controller\RulesApiController::update` — payload-level validation: rejects rules without a `target`, rejects forbidden targets (`groups`) with a pointer at `oidc_groups_mapping`, and rejects unknown targets with the supported list in the message.

### Known issues

- The existing test suite inherited from `oidc_groups_mapping` does not yet supply the new required `target` field, so `tests/Unit/Model/RuleTest.php`, `tests/Unit/Service/RuleEngineTest.php` and `tests/Unit/Controller/RulesApiControllerTest.php` will fail until the M6 test refresh. PHP syntax is clean on all production files (`php -l`).

### Roadmap (V0.1.0 alpha milestones)

- **M1** — Scaffold + rename (done)
- **M2** — `TargetRegistry` + `Rule.target` field validation (done)
- **M3** — `AttributeMappingListener` + `iss`-based provider scoping (done)

### M3 additions

- `Service\ProviderResolver` — resolves the `iss` token claim to a `user_oidc` provider identifier. Matches by URL path between the iss and each provider's `discoveryEndpoint` (handles dev/prod hostname splits where the IdP is reachable on a public hostname for end users and on an internal hostname for the NC backend).
- `Listener\AttributeMappingListener` — replaces the deprecated `GroupsMappingListener`. For each `AttributeMappedEvent`, it decodes the event attribute into a canonical target via `TargetRegistry`, resolves the provider, filters enabled rules by `target` + `providerIdentifier` match, applies them first-match-wins, and on the first match calls `setValue()` + `stopPropagation()`. Logs at debug level per rule applied (rule id, target, value, provider).
- `lib/AppInfo/Application.php` now registers `AttributeMappingListener` on `AttributeMappedEvent`. The old `GroupsMappingListener` and its unit test are removed.
- `Service\MappingService` and `Command\TestMapping` are left intact — they are not on the login path; their refactor moves to M4 alongside the `MappingResult` → scalar-value migration.
- **M3** — `AttributeMappingListener` + `iss`-based provider scoping
- **M4** — Full Mustache renderer with cross-claim templates
- **M5** — Frontend Vue: target dropdown, provider dropdown, accordion grouping by target, preset browser
- **M6** — phpunit + psalm + GitHub Actions CI
- **M7** — First marketplace submission

Earlier history of the rule engine, claim resolver and admin UI lives in the source repo: [oidc_groups_mapping](https://github.com/strobelpierre/nextcloud_oidc_groups_mapping).
