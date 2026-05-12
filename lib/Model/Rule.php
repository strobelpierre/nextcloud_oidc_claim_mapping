<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Model;

class Rule {

	private const VALID_TYPES = ['direct', 'prefix', 'map', 'conditional', 'template'];

	public const PROVIDER_ANY = '*';

	private function __construct(
		private string $id,
		private string $type,
		private bool $enabled,
		private string $claimPath,
		private string $target,
		private string $providerIdentifier,
		private array $config,
	) {
	}

	public static function fromArray(array $data): self {
		if (!isset($data['id']) || !is_string($data['id'])) {
			throw new \InvalidArgumentException('Rule must have a string "id"');
		}
		if (!isset($data['type']) || !in_array($data['type'], self::VALID_TYPES, true)) {
			throw new \InvalidArgumentException('Unknown rule type: ' . ($data['type'] ?? 'null'));
		}
		if (!isset($data['claimPath']) || !is_string($data['claimPath'])) {
			throw new \InvalidArgumentException('Rule must have a string "claimPath"');
		}
		if (!isset($data['target']) || !is_string($data['target']) || $data['target'] === '') {
			throw new \InvalidArgumentException('Rule must have a non-empty string "target"');
		}

		$providerIdentifier = $data['providerIdentifier'] ?? self::PROVIDER_ANY;
		if (!is_string($providerIdentifier) || $providerIdentifier === '') {
			throw new \InvalidArgumentException('Rule "providerIdentifier" must be a non-empty string');
		}

		return new self(
			$data['id'],
			$data['type'],
			$data['enabled'] ?? true,
			$data['claimPath'],
			$data['target'],
			$providerIdentifier,
			$data['config'] ?? [],
		);
	}

	public function getId(): string {
		return $this->id;
	}

	public function getType(): string {
		return $this->type;
	}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	public function getClaimPath(): string {
		return $this->claimPath;
	}

	public function getTarget(): string {
		return $this->target;
	}

	public function getProviderIdentifier(): string {
		return $this->providerIdentifier;
	}

	public function appliesToProvider(string $providerIdentifier): bool {
		return $this->providerIdentifier === self::PROVIDER_ANY
			|| $this->providerIdentifier === $providerIdentifier;
	}

	public function getConfig(): array {
		return $this->config;
	}

	public function toArray(): array {
		return [
			'id' => $this->id,
			'type' => $this->type,
			'enabled' => $this->enabled,
			'claimPath' => $this->claimPath,
			'target' => $this->target,
			'providerIdentifier' => $this->providerIdentifier,
			'config' => $this->config,
		];
	}
}
