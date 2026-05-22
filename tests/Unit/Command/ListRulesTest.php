<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Command;

use OCA\OidcClaimMapping\Command\ListRules;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ListRulesTest extends TestCase {

	private IAppConfig $appConfig;
	private ListRules $command;
	private CommandTester $tester;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->command = new ListRules($this->appConfig);
		$this->tester = new CommandTester($this->command);
	}

	public function testConfigure(): void {
		$this->assertSame('oidc-groups:list', $this->command->getName());
		$this->assertStringContainsString('List', $this->command->getDescription());
	}

	public function testExecuteWithNoRules(): void {
		$this->appConfig->method('getValueString')
			->with('oidc_claim_mapping', 'mapping_rules', '')
			->willReturn('');

		$status = $this->tester->execute([]);

		$this->assertSame(0, $status);
		$this->assertStringContainsString('No rules configured.', $this->tester->getDisplay());
	}

	public function testExecuteWithEmptyRuleCollection(): void {
		$this->appConfig->method('getValueString')
			->willReturn('{"version":1,"mode":"additive","rules":[]}');

		$status = $this->tester->execute([]);

		$this->assertSame(0, $status);
		$this->assertStringContainsString('No rules configured.', $this->tester->getDisplay());
	}

	public function testExecuteWithSingleEnabledRule(): void {
		$json = json_encode([
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
		$this->appConfig->method('getValueString')->willReturn($json);

		$status = $this->tester->execute([]);
		$display = $this->tester->getDisplay();

		$this->assertSame(0, $status);
		$this->assertStringContainsString('Mode: additive', $display);
		$this->assertStringContainsString('[enabled]', $display);
		$this->assertStringContainsString('r1', $display);
		$this->assertStringContainsString('direct', $display);
		$this->assertStringContainsString('name', $display);
	}

	public function testExecuteShowsDisabledStatus(): void {
		$json = json_encode([
			'version' => 1,
			'mode' => 'additive',
			'rules' => [
				[
					'id' => 'r1',
					'target' => 'displayName',
					'type' => 'direct',
					'claimPath' => 'name',
					'enabled' => false,
					'config' => [],
				],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($json);

		$this->tester->execute([]);

		$this->assertStringContainsString('[disabled]', $this->tester->getDisplay());
	}

	public function testExecuteListsMultipleRules(): void {
		$json = json_encode([
			'version' => 1,
			'mode' => 'replace',
			'rules' => [
				[
					'id' => 'r1',
					'target' => 'displayName',
					'type' => 'direct',
					'claimPath' => 'name',
					'enabled' => true,
					'config' => [],
				],
				[
					'id' => 'r2',
					'target' => 'email',
					'type' => 'template',
					'claimPath' => 'email',
					'enabled' => true,
					'config' => ['template' => '{{value}}'],
				],
			],
		]);
		$this->appConfig->method('getValueString')->willReturn($json);

		$this->tester->execute([]);
		$display = $this->tester->getDisplay();

		$this->assertStringContainsString('Mode: replace', $display);
		$this->assertStringContainsString('r1', $display);
		$this->assertStringContainsString('r2', $display);
		$this->assertStringContainsString('template', $display);
	}
}
