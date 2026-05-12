<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Service;

/**
 * Hardcoded registry of scalar user attributes that this app may override
 * through the `user_oidc` `AttributeMappedEvent`.
 *
 * V1 mirrors the user_oidc 7.4 set of `ProviderService::SETTING_MAPPING_*`
 * constants. The list is intentionally hardcoded rather than introspected
 * at runtime so:
 *
 *   - the supported surface is explicit in version control;
 *   - the admin UI dropdown stays stable across user_oidc upgrades;
 *   - a breaking change in user_oidc is caught by the test suite, not by
 *     a silent rule that no longer applies.
 *
 * The `groups` target is intentionally forbidden in V1 (see V3 roadmap in
 * README). Group mapping lives in the companion `oidc_groups_mapping` app.
 */
class TargetRegistry {

	/**
	 * Canonical target names accepted by V1 rules.
	 *
	 * Each entry MUST match the camelCase suffix of an `AttributeMappedEvent`
	 * attribute name (without the `mapping` prefix). For example,
	 * `mappingDisplayName` -> `displayName`.
	 */
	public const TARGETS = [
		'uid',
		'displayName',
		'email',
		'quota',
		'language',
		'locale',
		'phone',
		'website',
		'address',
		'twitter',
		'fediverse',
		'organisation',
		'role',
		'headline',
		'biography',
		'avatar',
		'gender',
	];

	/**
	 * Targets that are explicitly rejected by this app. Hitting one of these
	 * via the API returns HTTP 400 with a message pointing the admin at
	 * `oidc_groups_mapping`.
	 */
	public const FORBIDDEN_TARGETS = [
		'groups',
	];

	private const EVENT_ATTRIBUTE_PREFIX = 'mapping';

	/**
	 * @return list<string>
	 */
	public function getSupportedTargets(): array {
		return self::TARGETS;
	}

	public function isSupported(string $target): bool {
		return in_array($target, self::TARGETS, true);
	}

	public function isForbidden(string $target): bool {
		return in_array($target, self::FORBIDDEN_TARGETS, true);
	}

	/**
	 * Map a `user_oidc` event attribute name (e.g. `mappingDisplayName`) to
	 * the canonical target name (e.g. `displayName`). Returns `null` when the
	 * event attribute is unknown or unsupported.
	 */
	public function fromEventAttribute(string $eventAttribute): ?string {
		if (!str_starts_with($eventAttribute, self::EVENT_ATTRIBUTE_PREFIX)) {
			return null;
		}
		$candidate = lcfirst(substr($eventAttribute, strlen(self::EVENT_ATTRIBUTE_PREFIX)));
		return $this->isSupported($candidate) ? $candidate : null;
	}
}
