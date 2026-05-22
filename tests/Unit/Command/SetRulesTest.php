<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Command;

use OCA\OidcClaimMapping\Command\SetRules;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SetRulesTest extends TestCase {

	private IAppConfig $appConfig;
	private SetRules $command;
	private CommandTester $tester;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->command = new SetRules($this->appConfig);
		$this->tester = new CommandTester($this->command);
	}

	public function testConfigure(): void {
		$this->assertSame('oidc-groups:set', $this->command->getName());
		$this->assertStringContainsString('Set', $this->command->getDescription());

		$definition = $this->command->getDefinition();
		$this->assertTrue($definition->hasArgument('json'));
		$this->assertTrue($definition->getArgument('json')->isRequired());
	}

	public function testExecuteSavesValidJson(): void {
		$inputJson = json_encode([
			'version' => 1,
			'mode' => 'additive',
			'rules' => [
				[
					'id' => 'r1',
					'target' => 'displayName',
					'type' => 'direct',
					'claimPath' => 'name',
					'enabled' => true,
					'config' => [],
				],
			],
		]);

		$this->appConfig->expects($this->once())
			->method('setValueString')
			->with('oidc_claim_mapping', 'mapping_rules', $this->isType('string'));

		$status = $this->tester->execute(['json' => $inputJson]);

		$this->assertSame(0, $status);
		$this->assertStringContainsString('Saved 1 rules', $this->tester->getDisplay());
		$this->assertStringContainsString('additive', $this->tester->getDisplay());
	}

	public function testExecuteWithEmptyRulesSavesZeroCount(): void {
		$inputJson = json_encode([
			'version' => 1,
			'mode' => 'replace',
			'rules' => [],
		]);

		$this->appConfig->expects($this->once())->method('setValueString');

		$this->tester->execute(['json' => $inputJson]);

		$this->assertStringContainsString('Saved 0 rules', $this->tester->getDisplay());
		$this->assertStringContainsString('replace', $this->tester->getDisplay());
	}

	public function testExecuteWithInvalidJsonSavesEmptyCollection(): void {
		// RuleCollection::fromJson is tolerant: invalid JSON yields an empty additive collection
		$this->appConfig->expects($this->once())->method('setValueString');

		$status = $this->tester->execute(['json' => 'not-valid-json']);

		$this->assertSame(0, $status);
		$this->assertStringContainsString('Saved 0 rules', $this->tester->getDisplay());
		$this->assertStringContainsString('additive', $this->tester->getDisplay());
	}
}
