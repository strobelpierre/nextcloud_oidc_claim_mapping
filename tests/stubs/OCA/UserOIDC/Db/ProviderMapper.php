<?php

namespace OCA\UserOIDC\Db;

/**
 * Stub of OCA\UserOIDC\Db\ProviderMapper for unit-test isolation.
 * Mirrors the public surface used by ProviderResolver and RulesApiController.
 */
class ProviderMapper {
	/** @return Provider[] */
	public function getProviders(): array {
		return [];
	}

	public function getProvider(int $id): Provider {
		return new Provider();
	}
}
