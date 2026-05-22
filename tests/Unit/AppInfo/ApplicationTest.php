<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\AppInfo;

use OCA\OidcClaimMapping\AppInfo\Application;
use OCA\OidcClaimMapping\Listener\AttributeMappingListener;
use OCP\App\IAppManager;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\IServerContainer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ApplicationTest extends TestCase {

	public function testAppIdConstant(): void {
		$this->assertSame('oidc_claim_mapping', Application::APP_ID);
	}

	public function testConstructorInstantiates(): void {
		$app = new Application();
		$this->assertInstanceOf(Application::class, $app);
	}

	public function testConstructorAcceptsUrlParams(): void {
		// Constructor signature: __construct(array $urlParams = [])
		// Make sure it doesn't blow up with extra params.
		$app = new Application(['foo' => 'bar']);
		$this->assertInstanceOf(Application::class, $app);
	}

	public function testRegisterAddsEventListenerForAttributeMappedEvent(): void {
		$app = new Application();
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects($this->once())
			->method('registerEventListener')
			->with(
				'OCA\\UserOIDC\\Event\\AttributeMappedEvent',
				AttributeMappingListener::class,
			);

		$app->register($context);
	}

	public function testBootLogsWarningWhenUserOidcDisabled(): void {
		$app = new Application();

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->with('user_oidc')->willReturn(false);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with($this->stringContains('user_oidc'));

		$container = $this->createMock(IServerContainer::class);
		$container->method('get')->willReturnCallback(function (string $id) use ($appManager, $logger) {
			return match ($id) {
				IAppManager::class => $appManager,
				LoggerInterface::class => $logger,
				default => null,
			};
		});

		$context = $this->createMock(IBootContext::class);
		$context->method('getServerContainer')->willReturn($container);

		$app->boot($context);
	}

	public function testBootDoesNotWarnWhenUserOidcEnabled(): void {
		$app = new Application();

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->with('user_oidc')->willReturn(true);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->never())->method('warning');

		$container = $this->createMock(IServerContainer::class);
		$container->method('get')->willReturnCallback(function (string $id) use ($appManager, $logger) {
			return match ($id) {
				IAppManager::class => $appManager,
				LoggerInterface::class => $logger,
				default => null,
			};
		});

		$context = $this->createMock(IBootContext::class);
		$context->method('getServerContainer')->willReturn($container);

		$app->boot($context);
	}
}
