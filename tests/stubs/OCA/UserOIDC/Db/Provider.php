<?php

namespace OCA\UserOIDC\Db;

/**
 * Stub of OCA\UserOIDC\Db\Provider for unit-test isolation.
 * Mirrors the public surface used by ProviderResolver and RulesApiController.
 */
class Provider {
	private string $identifier = '';
	private ?string $discoveryEndpoint = null;

	public function setIdentifier(string $identifier): void {
		$this->identifier = $identifier;
	}

	public function getIdentifier(): string {
		return $this->identifier;
	}

	public function setDiscoveryEndpoint(?string $discoveryEndpoint): void {
		$this->discoveryEndpoint = $discoveryEndpoint;
	}

	public function getDiscoveryEndpoint(): ?string {
		return $this->discoveryEndpoint;
	}
}
