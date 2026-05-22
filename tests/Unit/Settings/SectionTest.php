<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Settings;

use OCA\OidcClaimMapping\Settings\Section;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class SectionTest extends TestCase {

	private IURLGenerator $urlGenerator;
	private Section $section;

	protected function setUp(): void {
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->section = new Section($this->urlGenerator);
	}

	public function testGetID(): void {
		$this->assertSame('oidc_claim_mapping', $this->section->getID());
	}

	public function testGetName(): void {
		$this->assertSame('OIDC Claim Mapping', $this->section->getName());
	}

	public function testGetPriority(): void {
		$this->assertSame(80, $this->section->getPriority());
	}

	public function testGetIconReturnsCoreGroupIconPath(): void {
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('core', 'actions/group.svg')
			->willReturn('/img/actions/group.svg');

		$this->assertSame('/img/actions/group.svg', $this->section->getIcon());
	}
}
