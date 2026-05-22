<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Model;

use OCA\OidcClaimMapping\Model\Rule;
use PHPUnit\Framework\TestCase;

class RuleTest extends TestCase {

	public function testEmptyProviderIdentifierThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('providerIdentifier');
		Rule::fromArray([
			'id' => 'r',
			'type' => 'direct',
			'claimPath' => 'name',
			'target' => 'displayName',
			'providerIdentifier' => '',
		]);
	}

	public function testNonStringProviderIdentifierThrows(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('providerIdentifier');
		Rule::fromArray([
			'id' => 'r',
			'type' => 'direct',
			'claimPath' => 'name',
			'target' => 'displayName',
			'providerIdentifier' => 42,
		]);
	}

	public function testConstructValidDirect(): void {
		$rule = Rule::fromArray([
			'id' => 'test-rule',
			'type' => 'direct',
			'enabled' => true,
			'claimPath' => 'department',
			'target' => 'displayName',
			'config' => [],
		]);

		$this->assertSame('test-rule', $rule->getId());
		$this->assertSame('direct', $rule->getType());
		$this->assertTrue($rule->isEnabled());
		$this->assertSame('department', $rule->getClaimPath());
		$this->assertSame('displayName', $rule->getTarget());
		$this->assertSame(Rule::PROVIDER_ANY, $rule->getProviderIdentifier());
		$this->assertSame([], $rule->getConfig());
	}

	public function testConstructValidPrefix(): void {
		$rule = Rule::fromArray([
			'id' => 'prefix-rule',
			'type' => 'prefix',
			'enabled' => true,
			'claimPath' => 'roles',
			'target' => 'displayName',
			'config' => ['prefix' => 'role_'],
		]);

		$this->assertSame('prefix', $rule->getType());
		$this->assertSame(['prefix' => 'role_'], $rule->getConfig());
	}

	public function testConstructValidMap(): void {
		$rule = Rule::fromArray([
			'id' => 'map-rule',
			'type' => 'map',
			'enabled' => true,
			'claimPath' => 'domain',
			'target' => 'organisation',
			'config' => [
				'values' => ['example.com' => 'Staff'],
				'unmappedPolicy' => 'ignore',
			],
		]);

		$this->assertSame('map', $rule->getType());
	}

	public function testConstructValidConditional(): void {
		$rule = Rule::fromArray([
			'id' => 'cond-rule',
			'type' => 'conditional',
			'enabled' => true,
			'claimPath' => 'userType',
			'target' => 'role',
			'config' => [
				'operator' => 'equals',
				'value' => 'EXTERNAL',
				'groups' => ['External-Users'],
			],
		]);

		$this->assertSame('conditional', $rule->getType());
	}

	public function testConstructValidTemplate(): void {
		$rule = Rule::fromArray([
			'id' => 'tpl-rule',
			'type' => 'template',
			'enabled' => true,
			'claimPath' => 'department',
			'target' => 'headline',
			'config' => ['template' => 'dept_{{value}}'],
		]);

		$this->assertSame('template', $rule->getType());
	}

	public function testProviderIdentifierExplicit(): void {
		$rule = Rule::fromArray([
			'id' => 'scoped',
			'type' => 'direct',
			'enabled' => true,
			'claimPath' => 'name',
			'target' => 'displayName',
			'providerIdentifier' => 'eu-login-prod',
			'config' => [],
		]);

		$this->assertSame('eu-login-prod', $rule->getProviderIdentifier());
		$this->assertTrue($rule->appliesToProvider('eu-login-prod'));
		$this->assertFalse($rule->appliesToProvider('keycloak-dev'));
		$this->assertFalse($rule->appliesToProvider(Rule::PROVIDER_ANY));
	}

	public function testProviderIdentifierWildcardAppliesEverywhere(): void {
		$rule = Rule::fromArray([
			'id' => 'wild',
			'type' => 'direct',
			'enabled' => true,
			'claimPath' => 'name',
			'target' => 'displayName',
			'providerIdentifier' => Rule::PROVIDER_ANY,
			'config' => [],
		]);

		$this->assertTrue($rule->appliesToProvider('any-id'));
		$this->assertTrue($rule->appliesToProvider(Rule::PROVIDER_ANY));
	}

	public function testUnknownTypeThrowsException(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown rule type');

		Rule::fromArray([
			'id' => 'bad',
			'type' => 'unknown_type',
			'enabled' => true,
			'claimPath' => 'foo',
			'target' => 'displayName',
			'config' => [],
		]);
	}

	public function testMissingIdThrowsException(): void {
		$this->expectException(\InvalidArgumentException::class);

		Rule::fromArray([
			'type' => 'direct',
			'enabled' => true,
			'claimPath' => 'foo',
			'target' => 'displayName',
			'config' => [],
		]);
	}

	public function testMissingClaimPathThrowsException(): void {
		$this->expectException(\InvalidArgumentException::class);

		Rule::fromArray([
			'id' => 'test',
			'type' => 'direct',
			'enabled' => true,
			'target' => 'displayName',
			'config' => [],
		]);
	}

	public function testMissingTargetThrowsException(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('target');

		Rule::fromArray([
			'id' => 'test',
			'type' => 'direct',
			'enabled' => true,
			'claimPath' => 'name',
			'config' => [],
		]);
	}

	public function testEmptyTargetThrowsException(): void {
		$this->expectException(\InvalidArgumentException::class);

		Rule::fromArray([
			'id' => 'test',
			'type' => 'direct',
			'enabled' => true,
			'claimPath' => 'name',
			'target' => '',
			'config' => [],
		]);
	}

	public function testDisabledPreserved(): void {
		$rule = Rule::fromArray([
			'id' => 'disabled-rule',
			'type' => 'direct',
			'enabled' => false,
			'claimPath' => 'department',
			'target' => 'displayName',
			'config' => [],
		]);

		$this->assertFalse($rule->isEnabled());
	}

	public function testDefaultEnabledIsTrue(): void {
		$rule = Rule::fromArray([
			'id' => 'default-enabled',
			'type' => 'direct',
			'claimPath' => 'department',
			'target' => 'displayName',
			'config' => [],
		]);

		$this->assertTrue($rule->isEnabled());
	}

	public function testToArrayRoundTrip(): void {
		$data = [
			'id' => 'test',
			'type' => 'direct',
			'enabled' => true,
			'claimPath' => 'department',
			'target' => 'displayName',
			'providerIdentifier' => Rule::PROVIDER_ANY,
			'config' => [],
		];
		$rule = Rule::fromArray($data);
		$this->assertSame($data, $rule->toArray());
	}
}
