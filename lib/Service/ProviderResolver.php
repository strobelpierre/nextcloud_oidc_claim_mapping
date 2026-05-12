<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Service;

use OCA\UserOIDC\Db\ProviderMapper;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Resolves the canonical `user_oidc` provider identifier from the `iss` claim
 * found in the OIDC token. Rules can then be scoped to a provider via their
 * `providerIdentifier` field (`*` means "any provider").
 *
 * The match is done against `ProviderMapper::getProviders()` and the
 * `discoveryEndpoint` field of each Provider entity. We compare the
 * `iss` (issuer base URL) against the discovery endpoint prefix because
 * OIDC discovery endpoints conventionally live at `<iss>/.well-known/openid-configuration`.
 */
class ProviderResolver {

	public function __construct(
		private ProviderMapper $providerMapper,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Return the provider identifier that matches the given `iss`, or `null`
	 * if no provider matches (or `iss` is missing/blank).
	 *
	 * We compare the URL path of `iss` to the URL path of each provider's
	 * `discoveryEndpoint` rather than the full URL. This handles common
	 * dev/prod splits where the IdP is reachable on one hostname from end
	 * users (`iss` in the token) and on another hostname from the NC
	 * backend (`discoveryEndpoint` stored in `oc_user_oidc_providers`):
	 *   iss       = https://idp.example.com/realms/myrealm
	 *   discovery = http://idp-internal:8080/realms/myrealm/.well-known/openid-configuration
	 * The OIDC convention says discovery = `<issuer>/.well-known/openid-configuration`,
	 * so the discovery path must start with the iss path.
	 */
	public function resolveByIss(?string $iss): ?string {
		if ($iss === null || $iss === '') {
			return null;
		}

		$issPath = parse_url($iss, PHP_URL_PATH);
		if (!is_string($issPath) || $issPath === '') {
			$issPath = '/';
		}
		$issPath = rtrim($issPath, '/');

		try {
			$providers = $this->providerMapper->getProviders();
		} catch (Throwable $e) {
			$this->logger->error('Failed to enumerate user_oidc providers: {msg}', [
				'msg' => $e->getMessage(),
			]);
			return null;
		}

		foreach ($providers as $provider) {
			$discovery = $provider->getDiscoveryEndpoint();
			if ($discovery === null || $discovery === '') {
				continue;
			}
			$discoveryPath = parse_url($discovery, PHP_URL_PATH);
			if (!is_string($discoveryPath) || $discoveryPath === '') {
				continue;
			}
			// Discovery path must equal `iss path + /.well-known/openid-configuration`,
			// or fall back to a simple prefix match for IdPs that use a non-standard layout.
			if (
				$discoveryPath === $issPath . '/.well-known/openid-configuration'
				|| ($issPath !== '' && str_starts_with($discoveryPath, $issPath . '/'))
			) {
				return $provider->getIdentifier();
			}
		}

		$this->logger->debug('No user_oidc provider matches iss "{iss}"', [
			'iss' => $iss,
		]);
		return null;
	}
}
