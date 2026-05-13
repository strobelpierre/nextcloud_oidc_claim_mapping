<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Controller;

use OCA\OidcClaimMapping\Controller\RulesApiController;
use OCA\OidcClaimMapping\Service\ClaimResolver;
use OCA\OidcClaimMapping\Service\MustacheRenderer;
use OCA\OidcClaimMapping\Service\RuleEngine;
use OCA\OidcClaimMapping\Service\TargetRegistry;
use OCA\UserOIDC\Db\ProviderMapper;
use OCP\IAppConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RulesApiControllerTest extends TestCase {

	private RulesApiController $controller;
	private IAppConfig $appConfig;
	private ProviderMapper $providerMapper;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->providerMapper = $this->createMock(ProviderMapper::class);
		$request = $this->createMock(IRequest::class);

		$mustache = new MustacheRenderer();
		$ruleEngine = new RuleEngine(new ClaimResolver(), $mustache, new NullLogger());

		$this->controller = new RulesApiController(
			$request,
			$this->appConfig,
			$ruleEngine,
			new TargetRegistry(),
			$mustache,
			$this->providerMapper,
		);
	}

	// === index ===

	public function testIndexReturnsEmptyCollection(): void {
		$this->appConfig->method('getValueString')->willReturn('');

		$response = $this->controller->index();
		$data = $response->getData();

		self::assertSame(1, $data['version']);
		self::assertSame('additive', $data['mode']);
		self::assertSame([], $data['rules']);
		self::assertSame(200, $response->getStatus());
	}

	public function testIndexReturnsExistingRules(): void {
		$json = json_encode([
			'version' => 1,
			'mode' => 'replace',
			'rules' => [
				[
					'id' => 'r1',
					'type' => 'direct',
					'enabled' => true,
					'claimPath' => 'department',
					'target' => 'displayName',
					'config' => [],
				],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($json);

		$response = $this->controller->index();
		$data = $response->getData();

		self::assertSame(1, $data['version']);
		self::assertSame('replace', $data['mode']);
		self::assertCount(1, $data['rules']);
		self::assertSame('r1', $data['rules'][0]['id']);
		self::assertSame('displayName', $data['rules'][0]['target']);
	}

	// === update ===

	public function testUpdateSavesValidRules(): void {
		$rules = json_encode([
			'version' => 1,
			'mode' => 'additive',
			'rules' => [
				[
					'id' => 'test',
					'type' => 'prefix',
					'enabled' => true,
					'claimPath' => 'roles',
					'target' => 'displayName',
					'config' => ['prefix' => 'r_'],
				],
			],
		]);

		$this->appConfig->expects(self::once())->method('setValueString');

		$response = $this->controller->update($rules);
		self::assertSame(200, $response->getStatus());
		self::assertCount(1, $response->getData()['rules']);
	}

	public function testUpdateRejectsInvalidJson(): void {
		$response = $this->controller->update('not json{{{');

		self::assertSame(400, $response->getStatus());
		self::assertSame('Invalid JSON', $response->getData()['message']);
	}

	public function testUpdateRejectsMissingTarget(): void {
		$rules = json_encode([
			'version' => 1,
			'mode' => 'additive',
			'rules' => [
				['id' => 'bad', 'type' => 'direct', 'enabled' => true, 'claimPath' => 'x', 'config' => []],
			],
		]);

		$response = $this->controller->update($rules);

		self::assertSame(400, $response->getStatus());
		self::assertStringContainsString("'target'", $response->getData()['message']);
	}

	public function testUpdateRejectsForbiddenGroupsTarget(): void {
		$rules = json_encode([
			'version' => 1,
			'rules' => [
				['id' => 'g', 'type' => 'direct', 'enabled' => true, 'claimPath' => 'x', 'target' => 'groups', 'config' => []],
			],
		]);

		$response = $this->controller->update($rules);

		self::assertSame(400, $response->getStatus());
		self::assertStringContainsString('groups', $response->getData()['message']);
		self::assertStringContainsString('oidc_groups_mapping', $response->getData()['message']);
	}

	public function testUpdateRejectsUnknownTarget(): void {
		$rules = json_encode([
			'version' => 1,
			'rules' => [
				['id' => 'u', 'type' => 'direct', 'enabled' => true, 'claimPath' => 'x', 'target' => 'made_up_attribute', 'config' => []],
			],
		]);

		$response = $this->controller->update($rules);

		self::assertSame(400, $response->getStatus());
		self::assertStringContainsString("Unknown target 'made_up_attribute'", $response->getData()['message']);
		self::assertStringContainsString('displayName', $response->getData()['message']);
	}

	public function testUpdateRejectsInvalidMustacheTemplate(): void {
		$rules = json_encode([
			'version' => 1,
			'rules' => [
				[
					'id' => 't',
					'type' => 'template',
					'enabled' => true,
					'claimPath' => 'name',
					'target' => 'displayName',
					// Unbalanced section opens with {{# but never closes
					'config' => ['template' => '{{#claims.country}}({{value}}'],
				],
			],
		]);

		$response = $this->controller->update($rules);

		self::assertSame(400, $response->getStatus());
		self::assertStringContainsString('Mustache template', $response->getData()['message']);
	}

	public function testUpdateRejectsTemplateWithoutTemplateField(): void {
		$rules = json_encode([
			'version' => 1,
			'rules' => [
				[
					'id' => 't',
					'type' => 'template',
					'enabled' => true,
					'claimPath' => 'name',
					'target' => 'displayName',
					'config' => [],
				],
			],
		]);

		$response = $this->controller->update($rules);

		self::assertSame(400, $response->getStatus());
		self::assertStringContainsString('config.template', $response->getData()['message']);
	}

	public function testUpdateWithDisabledRule(): void {
		$rules = json_encode([
			'version' => 1,
			'mode' => 'replace',
			'rules' => [
				['id' => 'r1', 'type' => 'direct', 'enabled' => true, 'claimPath' => 'a', 'target' => 'displayName', 'config' => []],
				['id' => 'r2', 'type' => 'prefix', 'enabled' => false, 'claimPath' => 'b', 'target' => 'email', 'config' => ['prefix' => 'p_']],
			],
		]);

		$this->appConfig->expects(self::once())->method('setValueString');

		$response = $this->controller->update($rules);
		$data = $response->getData();

		self::assertSame(2, $data['rules_count']);
		self::assertSame(1, $data['enabled_count']);
	}

	// === simulate ===

	public function testSimulateRejectsInvalidToken(): void {
		$response = $this->controller->simulate('not json');

		self::assertSame(400, $response->getStatus());
		self::assertSame('Invalid JSON token', $response->getData()['message']);
	}

	public function testSimulateWithDirectRule(): void {
		$rulesJson = json_encode([
			'version' => 1,
			'mode' => 'additive',
			'rules' => [
				['id' => 'dept', 'type' => 'direct', 'enabled' => true, 'claimPath' => 'department', 'target' => 'displayName', 'config' => []],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($rulesJson);

		$response = $this->controller->simulate('{"department":"Engineering"}');
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertSame('additive', $data['mode']);
		self::assertCount(1, $data['ruleResults']);
		self::assertTrue($data['ruleResults'][0]['matched']);
		self::assertSame(['Engineering'], $data['ruleResults'][0]['groups']);
		self::assertSame(['Engineering'], $data['finalGroups']);
	}

	public function testSimulateAdditiveMode(): void {
		$rulesJson = json_encode([
			'version' => 1,
			'mode' => 'additive',
			'rules' => [
				['id' => 'dept', 'type' => 'direct', 'enabled' => true, 'claimPath' => 'department', 'target' => 'displayName', 'config' => []],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($rulesJson);

		$response = $this->controller->simulate('{"department":"IT"}', '["users"]');
		$data = $response->getData();

		self::assertSame(['users', 'IT'], $data['finalGroups']);
		self::assertSame(['users'], $data['existingGroups']);
	}

	public function testSimulateReplaceModeNoMatch(): void {
		$rulesJson = json_encode([
			'version' => 1,
			'mode' => 'replace',
			'rules' => [
				['id' => 'dept', 'type' => 'direct', 'enabled' => true, 'claimPath' => 'department', 'target' => 'displayName', 'config' => []],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($rulesJson);

		$response = $this->controller->simulate('{"roles":["admin"]}', '["users"]');
		$data = $response->getData();

		self::assertSame(['users'], $data['finalGroups']);
	}

	// === targets / providers ===

	public function testTargetsEndpointReturnsRegistry(): void {
		$response = $this->controller->targets();
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertIsArray($data['targets']);
		self::assertContains('displayName', $data['targets']);
		self::assertContains('email', $data['targets']);
		self::assertNotContains('groups', $data['targets']);
	}

	public function testProvidersEndpointReturnsList(): void {
		$p1 = new \OCA\UserOIDC\Db\Provider();
		$p1->setIdentifier('eu-login-prod');
		$p1->setDiscoveryEndpoint('https://idp.example.com/.well-known/openid-configuration');

		$this->providerMapper->method('getProviders')->willReturn([$p1]);

		$response = $this->controller->providers();
		$data = $response->getData();

		self::assertSame(200, $response->getStatus());
		self::assertCount(1, $data['providers']);
		self::assertSame('eu-login-prod', $data['providers'][0]['identifier']);
	}

	public function testProvidersEndpointGracefulOnException(): void {
		$this->providerMapper->method('getProviders')
			->willThrowException(new \RuntimeException('DB down'));

		$response = $this->controller->providers();
		$data = $response->getData();

		self::assertSame([], $data['providers']);
		self::assertStringContainsString('DB down', $data['error']);
	}
}
