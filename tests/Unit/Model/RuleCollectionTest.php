<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Model;

use OCA\OidcClaimMapping\Model\RuleCollection;
use PHPUnit\Framework\TestCase;

class RuleCollectionTest extends TestCase {

	public function testFromJsonEmpty(): void {
		$collection = RuleCollection::fromJson('');
		$this->assertCount(0, $collection->getRules());
		$this->assertSame('additive', $collection->getMode());
	}

	public function testFromJsonInvalid(): void {
		$collection = RuleCollection::fromJson('{invalid json');
		$this->assertCount(0, $collection->getRules());
	}

	public function testFromJsonNoRules(): void {
		$collection = RuleCollection::fromJson('{"version":1,"mode":"additive","rules":[]}');
		$this->assertCount(0, $collection->getRules());
		$this->assertSame('additive', $collection->getMode());
	}

	public function testFromJsonMultipleRules(): void {
		$json = json_encode([
			'version' => 1,
			'mode' => 'replace',
			'rules' => [
				[
					'id' => 'rule1',
					'type' => 'direct',
					'enabled' => true,
					'claimPath' => 'department',
					'target' => 'displayName',
					'config' => [],
				],
				[
					'id' => 'rule2',
					'type' => 'prefix',
					'enabled' => false,
					'claimPath' => 'roles',
					'target' => 'displayName',
					'config' => ['prefix' => 'role_'],
				],
			],
		]);

		$collection = RuleCollection::fromJson($json);
		$this->assertCount(2, $collection->getRules());
		$this->assertSame('replace', $collection->getMode());
	}

	public function testFromJsonSkipsInvalidRules(): void {
		$json = json_encode([
			'version' => 1,
			'mode' => 'additive',
			'rules' => [
				[
					'id' => 'valid',
					'type' => 'direct',
					'enabled' => true,
					'claimPath' => 'department',
					'target' => 'displayName',
					'config' => [],
				],
				[
					'id' => 'invalid',
					'type' => 'bogus_type',
					'enabled' => true,
					'claimPath' => 'foo',
					'target' => 'displayName',
					'config' => [],
				],
			],
		]);

		$collection = RuleCollection::fromJson($json);
		$this->assertCount(1, $collection->getRules());
	}

	public function testGetEnabledRules(): void {
		$json = json_encode([
			'version' => 1,
			'mode' => 'additive',
			'rules' => [
				[
					'id' => 'enabled1',
					'type' => 'direct',
					'enabled' => true,
					'claimPath' => 'a',
					'target' => 'displayName',
					'config' => [],
				],
				[
					'id' => 'disabled1',
					'type' => 'direct',
					'enabled' => false,
					'claimPath' => 'b',
					'target' => 'displayName',
					'config' => [],
				],
				[
					'id' => 'enabled2',
					'type' => 'prefix',
					'enabled' => true,
					'claimPath' => 'c',
					'target' => 'displayName',
					'config' => ['prefix' => 'p_'],
				],
			],
		]);

		$collection = RuleCollection::fromJson($json);
		$enabled = $collection->getEnabledRules();
		$this->assertCount(2, $enabled);
		$this->assertSame('enabled1', $enabled[0]->getId());
		$this->assertSame('enabled2', $enabled[1]->getId());
	}

	public function testDefaultModeIsAdditive(): void {
		$json = json_encode([
			'version' => 1,
			'rules' => [],
		]);
		$collection = RuleCollection::fromJson($json);
		$this->assertSame('additive', $collection->getMode());
	}

	public function testToJson(): void {
		$json = json_encode([
			'version' => 1,
			'mode' => 'additive',
			'rules' => [
				[
					'id' => 'rule1',
					'type' => 'direct',
					'enabled' => true,
					'claimPath' => 'department',
					'target' => 'displayName',
					'config' => [],
				],
			],
		]);

		$collection = RuleCollection::fromJson($json);
		$output = $collection->toJson();
		$decoded = json_decode($output, true);
		$this->assertSame(1, $decoded['version']);
		$this->assertSame('additive', $decoded['mode']);
		$this->assertCount(1, $decoded['rules']);
	}
}
