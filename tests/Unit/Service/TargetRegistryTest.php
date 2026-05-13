<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Service;

use OCA\OidcClaimMapping\Service\TargetRegistry;
use PHPUnit\Framework\TestCase;

class TargetRegistryTest extends TestCase {

	private TargetRegistry $registry;

	protected function setUp(): void {
		$this->registry = new TargetRegistry();
	}

	public function testKnownTargetsAreSupported(): void {
		$this->assertTrue($this->registry->isSupported('displayName'));
		$this->assertTrue($this->registry->isSupported('email'));
		$this->assertTrue($this->registry->isSupported('quota'));
		$this->assertTrue($this->registry->isSupported('uid'));
	}

	public function testUnknownTargetIsNotSupported(): void {
		$this->assertFalse($this->registry->isSupported('made_up_attribute'));
		$this->assertFalse($this->registry->isSupported(''));
		// case-sensitive: "displayname" lowercase is not the canonical name
		$this->assertFalse($this->registry->isSupported('displayname'));
	}

	public function testGroupsIsForbidden(): void {
		$this->assertTrue($this->registry->isForbidden('groups'));
		// `groups` is rejected explicitly, not just absent from the supported list
		$this->assertFalse($this->registry->isSupported('groups'));
	}

	public function testFromEventAttributeMapsKnown(): void {
		$this->assertSame('displayName', $this->registry->fromEventAttribute('mappingDisplayName'));
		$this->assertSame('email', $this->registry->fromEventAttribute('mappingEmail'));
		$this->assertSame('quota', $this->registry->fromEventAttribute('mappingQuota'));
	}

	public function testFromEventAttributeReturnsNullForUnknown(): void {
		// mappingGroups exists in user_oidc but we don't accept it (V1 scope)
		$this->assertNull($this->registry->fromEventAttribute('mappingGroups'));
		// totally unknown attribute
		$this->assertNull($this->registry->fromEventAttribute('mappingNopeNotHere'));
	}

	public function testFromEventAttributeReturnsNullWithoutPrefix(): void {
		$this->assertNull($this->registry->fromEventAttribute('displayName'));
		$this->assertNull($this->registry->fromEventAttribute(''));
	}

	public function testSupportedTargetsListIsStable(): void {
		$targets = $this->registry->getSupportedTargets();
		$this->assertGreaterThanOrEqual(15, count($targets));
		$this->assertContains('displayName', $targets);
		$this->assertContains('email', $targets);
		$this->assertNotContains('groups', $targets);
	}
}
