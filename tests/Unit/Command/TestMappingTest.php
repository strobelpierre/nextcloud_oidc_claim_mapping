<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Command;

use OCA\OidcClaimMapping\Command\TestMapping;
use OCA\OidcClaimMapping\Service\MappingService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class TestMappingTest extends TestCase {

	private MappingService $mappingService;
	private TestMapping $command;
	private CommandTester $tester;

	protected function setUp(): void {
		$this->mappingService = $this->createMock(MappingService::class);
		$this->command = new TestMapping($this->mappingService);
		$this->tester = new CommandTester($this->command);
	}

	public function testConfigure(): void {
		$this->assertSame('oidc-groups:test', $this->command->getName());
		$this->assertStringContainsString('Test', $this->command->getDescription());

		$definition = $this->command->getDefinition();
		$this->assertTrue($definition->hasOption('token'));
		$this->assertTrue($definition->hasOption('existing'));
	}

	public function testExecuteWithoutTokenReturnsError(): void {
		$status = $this->tester->execute([]);

		$this->assertSame(1, $status);
		$this->assertStringContainsString('--token is required', $this->tester->getDisplay());
	}

	public function testExecuteWithInvalidJsonTokenReturnsError(): void {
		$status = $this->tester->execute(['--token' => 'not-json']);

		$this->assertSame(1, $status);
		$this->assertStringContainsString('Invalid JSON token', $this->tester->getDisplay());
	}

	public function testExecuteWithValidTokenNoMatchedRules(): void {
		$this->mappingService->method('process')->willReturn(null);

		$status = $this->tester->execute(['--token' => '{"name":"Jane"}']);

		$this->assertSame(0, $status);
		$this->assertStringContainsString('No rules matched', $this->tester->getDisplay());
	}

	public function testExecuteWithMatchedRulesDisplaysGroups(): void {
		$this->mappingService->method('process')->willReturn(['group-a', 'group-b']);

		$status = $this->tester->execute(['--token' => '{"name":"Jane"}']);

		$this->assertSame(0, $status);
		$display = $this->tester->getDisplay();
		$this->assertStringContainsString('Resulting groups', $display);
		$this->assertStringContainsString('group-a', $display);
		$this->assertStringContainsString('group-b', $display);
	}

	public function testExecuteWithExistingGroupsPassedToService(): void {
		$this->mappingService->expects($this->once())
			->method('process')
			->with(
				$this->isInstanceOf(\stdClass::class),
				['existing-group'],
			)
			->willReturn(['existing-group', 'new-group']);

		$this->tester->execute([
			'--token' => '{"sub":"jdoe"}',
			'--existing' => '["existing-group"]',
		]);
	}

	public function testExecuteWithInvalidExistingFallsBackToEmpty(): void {
		$this->mappingService->expects($this->once())
			->method('process')
			->with($this->isInstanceOf(\stdClass::class), [])
			->willReturn(null);

		$this->tester->execute([
			'--token' => '{"sub":"jdoe"}',
			'--existing' => 'not-json',
		]);
	}
}
