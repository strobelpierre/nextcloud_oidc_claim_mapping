<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Service;

use OCA\OidcClaimMapping\Service\MustacheRenderer;
use PHPUnit\Framework\TestCase;

class MustacheRendererTest extends TestCase {

	private MustacheRenderer $renderer;

	protected function setUp(): void {
		$this->renderer = new MustacheRenderer();
	}

	public function testValueOnly(): void {
		$out = $this->renderer->render('Hello {{value}}!', 'World', (object)[]);
		$this->assertSame('Hello World!', $out);
	}

	public function testCrossClaim(): void {
		$out = $this->renderer->render(
			'{{value}} ({{claims.country}})',
			'Alice Test',
			(object)['country' => 'PL'],
		);
		$this->assertSame('Alice Test (PL)', $out);
	}

	public function testNestedClaim(): void {
		$out = $this->renderer->render(
			'{{value}} - {{claims.address.country}}',
			'Bob',
			(object)['address' => (object)['country' => 'BE']],
		);
		$this->assertSame('Bob - BE', $out);
	}

	public function testSectionTruthy(): void {
		$out = $this->renderer->render(
			'{{value}}{{#claims.flag}} (flagged){{/claims.flag}}',
			'Alice',
			(object)['flag' => true],
		);
		$this->assertSame('Alice (flagged)', $out);
	}

	public function testSectionFalsy(): void {
		$out = $this->renderer->render(
			'{{value}}{{#claims.flag}} (flagged){{/claims.flag}}',
			'Alice',
			(object)['flag' => false],
		);
		$this->assertSame('Alice', $out);
	}

	public function testSectionMissingClaim(): void {
		$out = $this->renderer->render(
			'{{value}}{{#claims.absent}} (suffix){{/claims.absent}}',
			'Alice',
			(object)[],
		);
		$this->assertSame('Alice', $out);
	}

	public function testHtmlNotEscaped(): void {
		// Display names can contain quotes and ampersands; the renderer must
		// not escape them (we don't write into HTML).
		$out = $this->renderer->render(
			'{{value}}',
			'O\'Brien & Sons',
			(object)[],
		);
		$this->assertSame('O\'Brien & Sons', $out);
	}

	public function testValidateAcceptsValidTemplate(): void {
		$this->renderer->validate('Hello {{value}} ({{claims.country}})');
		$this->renderer->validate('{{#claims.flag}}seen{{/claims.flag}}');
		$this->addToAssertionCount(1);
	}

	public function testValidateRejectsUnclosedSection(): void {
		$this->expectException(\Throwable::class);
		$this->renderer->validate('{{#claims.flag}} oops, no close tag');
	}
}
