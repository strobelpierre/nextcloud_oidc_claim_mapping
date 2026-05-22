<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Service;

use OCA\OidcClaimMapping\Service\ProviderResolver;
use OCA\UserOIDC\Db\Provider;
use OCA\UserOIDC\Db\ProviderMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ProviderResolverTest extends TestCase {

	private function makeResolver(array $providers): ProviderResolver {
		$mapper = $this->createMock(ProviderMapper::class);
		$mapper->method('getProviders')->willReturn($providers);
		return new ProviderResolver($mapper, new NullLogger());
	}

	private function provider(string $identifier, ?string $discovery): Provider {
		$p = new Provider();
		$p->setIdentifier($identifier);
		$p->setDiscoveryEndpoint($discovery);
		return $p;
	}

	public function testReturnsNullOnEmptyIss(): void {
		$resolver = $this->makeResolver([]);
		$this->assertNull($resolver->resolveByIss(null));
		$this->assertNull($resolver->resolveByIss(''));
	}

	public function testMatchesStandardDiscoveryEndpoint(): void {
		$resolver = $this->makeResolver([
			$this->provider('eu-login-prod', 'https://idp.example.com/realms/prod/.well-known/openid-configuration'),
		]);
		$this->assertSame(
			'eu-login-prod',
			$resolver->resolveByIss('https://idp.example.com/realms/prod'),
		);
	}

	public function testMatchesPathOnlyAcrossHostnames(): void {
		// Common dev/prod split: end users see localhost:8999, NC backend
		// sees keycloak:8080 — same realm path, different hostname/port.
		$resolver = $this->makeResolver([
			$this->provider('keycloak-dev', 'http://keycloak:8080/realms/myrealm/.well-known/openid-configuration'),
		]);
		$this->assertSame(
			'keycloak-dev',
			$resolver->resolveByIss('http://localhost:8999/realms/myrealm'),
		);
	}

	public function testReturnsNullWhenNoProviderMatches(): void {
		$resolver = $this->makeResolver([
			$this->provider('other', 'https://other.example.com/realms/other/.well-known/openid-configuration'),
		]);
		$this->assertNull($resolver->resolveByIss('https://idp.example.com/realms/prod'));
	}

	public function testSkipsProvidersWithoutDiscoveryEndpoint(): void {
		$resolver = $this->makeResolver([
			$this->provider('no-discovery', null),
			$this->provider('good', 'https://idp.example.com/realms/prod/.well-known/openid-configuration'),
		]);
		$this->assertSame('good', $resolver->resolveByIss('https://idp.example.com/realms/prod'));
	}

	public function testIssWithoutPathFallsBackToRoot(): void {
		// iss without a path component → resolver normalizes the iss path to '/'
		// and only matches a provider whose discovery sits at the root.
		$resolver = $this->makeResolver([
			$this->provider('root', 'https://idp.example.com/.well-known/openid-configuration'),
		]);
		$this->assertSame('root', $resolver->resolveByIss('https://idp.example.com'));
	}

	public function testSkipsProviderWithDiscoveryHavingNoPath(): void {
		// Discovery URL without a path component (just scheme + host) is skipped.
		$resolver = $this->makeResolver([
			$this->provider('no-path', 'https://idp.example.com'),
			$this->provider('ok', 'https://idp.example.com/realms/prod/.well-known/openid-configuration'),
		]);
		$this->assertSame('ok', $resolver->resolveByIss('https://idp.example.com/realms/prod'));
	}

	public function testGracefulOnMapperException(): void {
		$mapper = $this->createMock(ProviderMapper::class);
		$mapper->method('getProviders')->willThrowException(new \RuntimeException('DB down'));
		$resolver = new ProviderResolver($mapper, new NullLogger());

		// Must not throw, must return null
		$this->assertNull($resolver->resolveByIss('https://idp.example.com/realms/prod'));
	}
}
