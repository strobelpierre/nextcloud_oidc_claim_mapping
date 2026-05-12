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

### Roadmap (V0.1.0 alpha milestones)

- **M1** — Scaffold + rename (in progress)
- **M2** — `TargetRegistry` + `Rule.target` field validation
- **M3** — `AttributeMappingListener` + `iss`-based provider scoping
- **M4** — Full Mustache renderer with cross-claim templates
- **M5** — Frontend Vue: target dropdown, provider dropdown, accordion grouping by target, preset browser
- **M6** — phpunit + psalm + GitHub Actions CI
- **M7** — First marketplace submission

Earlier history of the rule engine, claim resolver and admin UI lives in the source repo: [oidc_groups_mapping](https://github.com/strobelpierre/nextcloud_oidc_groups_mapping).
