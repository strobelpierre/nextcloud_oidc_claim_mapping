<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Listener;

use OCA\OidcClaimMapping\Listener\AttributeMappingListener;
use OCA\OidcClaimMapping\Service\ClaimResolver;
use OCA\OidcClaimMapping\Service\MustacheRenderer;
use OCA\OidcClaimMapping\Service\ProviderResolver;
use OCA\OidcClaimMapping\Service\RuleEngine;
use OCA\OidcClaimMapping\Service\TargetRegistry;
use OCA\UserOIDC\Db\ProviderMapper;
use OCA\UserOIDC\Event\AttributeMappedEvent;
use OCP\EventDispatcher\Event;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AttributeMappingListenerTest extends TestCase {

	private IAppConfig $appConfig;
	private ProviderMapper $providerMapper;
	private AttributeMappingListener $listener;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->providerMapper = $this->createMock(ProviderMapper::class);
		$this->providerMapper->method('getProviders')->willReturn([]);

		$mustache = new MustacheRenderer();
		$engine = new RuleEngine(new ClaimResolver(), $mustache, new NullLogger());
		$registry = new TargetRegistry();
		$providerResolver = new ProviderResolver($this->providerMapper, new NullLogger());

		$this->listener = new AttributeMappingListener(
			$this->appConfig,
			$engine,
			$registry,
			$providerResolver,
			new NullLogger(),
		);
	}

	private function rules(array $rules): string {
		return json_encode(['version' => 1, 'mode' => 'additive', 'rules' => $rules]);
	}

	public function testIgnoresNonAttributeMappedEvent(): void {
		$event = new Event();
		$this->appConfig->expects(self::never())->method('getValueString');
		$this->listener->handle($event);
		$this->addToAssertionCount(1);
	}

	public function testIgnoresUnsupportedAttribute(): void {
		// `mappingGroups` is not in V1 scope — listener must return early
		// without even reading the rules
		$event = new AttributeMappedEvent('mappingGroups', (object)['name' => 'Alice']);
		$this->appConfig->expects(self::never())->method('getValueString');

		$this->listener->handle($event);

		$this->assertNull($event->getValue());
	}

	public function testNoRulesNoChange(): void {
		$event = new AttributeMappedEvent('mappingDisplayName', (object)['name' => 'Alice']);
		$this->appConfig->method('getValueString')->willReturn('');

		$this->listener->handle($event);

		$this->assertNull($event->getValue());
	}

	public function testAppliesMatchingRule(): void {
		$rules = $this->rules([
			[
				'id' => 'prefix-name',
				'type' => 'prefix',
				'enabled' => true,
				'claimPath' => 'name',
				'target' => 'displayName',
				'config' => ['prefix' => '[DEV] '],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($rules);

		$event = new AttributeMappedEvent('mappingDisplayName', (object)['name' => 'Alice Test']);
		$this->listener->handle($event);

		$this->assertSame('[DEV] Alice Test', $event->getValue());
		$this->assertTrue($event->isPropagationStopped());
	}

	public function testSkipsRulesForOtherTargets(): void {
		// A rule targeting `email` must not run on a mappingDisplayName event
		$rules = $this->rules([
			[
				'id' => 'email-rule',
				'type' => 'direct',
				'enabled' => true,
				'claimPath' => 'email',
				'target' => 'email',
				'config' => [],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($rules);

		$event = new AttributeMappedEvent('mappingDisplayName', (object)['email' => 'a@b.c']);
		$this->listener->handle($event);

		$this->assertNull($event->getValue());
		$this->assertFalse($event->isPropagationStopped());
	}

	public function testSkipsDisabledRule(): void {
		$rules = $this->rules([
			[
				'id' => 'disabled',
				'type' => 'prefix',
				'enabled' => false,
				'claimPath' => 'name',
				'target' => 'displayName',
				'config' => ['prefix' => '[X] '],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($rules);

		$event = new AttributeMappedEvent('mappingDisplayName', (object)['name' => 'Alice']);
		$this->listener->handle($event);

		$this->assertNull($event->getValue());
	}

	public function testFirstMatchWins(): void {
		$rules = $this->rules([
			[
				'id' => 'first',
				'type' => 'prefix',
				'enabled' => true,
				'claimPath' => 'name',
				'target' => 'displayName',
				'config' => ['prefix' => 'FIRST '],
			],
			[
				'id' => 'second',
				'type' => 'prefix',
				'enabled' => true,
				'claimPath' => 'name',
				'target' => 'displayName',
				'config' => ['prefix' => 'SECOND '],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($rules);

		$event = new AttributeMappedEvent('mappingDisplayName', (object)['name' => 'Alice']);
		$this->listener->handle($event);

		$this->assertSame('FIRST Alice', $event->getValue());
	}

	public function testWildcardRuleAppliesWhenProviderResolverFails(): void {
		// No provider known to the mapper → resolver returns null → wildcard rules apply
		$rules = $this->rules([
			[
				'id' => 'wild',
				'type' => 'direct',
				'enabled' => true,
				'claimPath' => 'name',
				'target' => 'displayName',
				'providerIdentifier' => '*',
				'config' => [],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($rules);

		$event = new AttributeMappedEvent(
			'mappingDisplayName',
			(object)['iss' => 'https://idp.example/realms/x', 'name' => 'Bob'],
		);
		$this->listener->handle($event);

		$this->assertSame('Bob', $event->getValue());
	}
}
