<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Note: the OCP\Util stub used here is force-loaded from tests/bootstrap.php
 * so the real vendor class never gets autoloaded.
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Settings;

use OCA\OidcClaimMapping\Settings\AdminSettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use OCP\Util;
use PHPUnit\Framework\TestCase;

class AdminSettingsTest extends TestCase {

	private IAppConfig $appConfig;
	private IInitialState $initialState;
	private AdminSettings $settings;

	protected function setUp(): void {
		Util::resetForTests();
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->initialState = $this->createMock(IInitialState::class);
		$this->settings = new AdminSettings($this->appConfig, $this->initialState);
	}

	public function testGetSection(): void {
		$this->assertSame('oidc_claim_mapping', $this->settings->getSection());
	}

	public function testGetPriority(): void {
		$this->assertSame(50, $this->settings->getPriority());
	}

	public function testGetFormReturnsTemplateResponse(): void {
		$this->appConfig->method('getValueString')
			->with('oidc_claim_mapping', 'mapping_rules', '')
			->willReturn('');

		$this->initialState->expects($this->exactly(2))
			->method('provideInitialState');

		$response = $this->settings->getForm();

		$this->assertInstanceOf(TemplateResponse::class, $response);
	}

	public function testGetFormAddsAdminScript(): void {
		$this->appConfig->method('getValueString')->willReturn('');

		$this->settings->getForm();

		$this->assertArrayHasKey('oidc_claim_mapping', Util::$addedScripts);
		$this->assertContains('oidc_claim_mapping-main', Util::$addedScripts['oidc_claim_mapping']);
	}

	public function testGetFormProvidesRulesAndModeAsInitialState(): void {
		$json = json_encode([
			'version' => 1,
			'mode' => 'replace',
			'rules' => [],
		]);
		$this->appConfig->method('getValueString')->willReturn($json);

		$keys = [];
		$this->initialState->expects($this->exactly(2))
			->method('provideInitialState')
			->willReturnCallback(function (string $key, mixed $value) use (&$keys) {
				$keys[$key] = $value;
			});

		$this->settings->getForm();

		$this->assertArrayHasKey('rules', $keys);
		$this->assertArrayHasKey('mode', $keys);
		$this->assertSame('replace', $keys['mode']);
	}
}
