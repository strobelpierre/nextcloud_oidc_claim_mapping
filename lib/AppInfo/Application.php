<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\AppInfo;

use OCA\OidcClaimMapping\Listener\AttributeMappingListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Psr\Log\LoggerInterface;

class Application extends App implements IBootstrap {

	public const APP_ID = 'oidc_claim_mapping';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		// Pull in Composer-managed dependencies (Mustache, etc.). NC does not
		// auto-load app-level vendor autoload files in third-party apps; we
		// load it explicitly here so the renderer + any future dep resolves.
		$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
		if (file_exists($autoload)) {
			require_once $autoload;
		}
	}

	public function register(IRegistrationContext $context): void {
		// Use string literal to avoid triggering autoload during registration.
		// NC will only resolve the class when the event is actually dispatched.
		$context->registerEventListener(
			'OCA\\UserOIDC\\Event\\AttributeMappedEvent',
			AttributeMappingListener::class,
		);
	}

	public function boot(IBootContext $context): void {
		$appManager = $context->getServerContainer()->get(\OCP\App\IAppManager::class);
		if (!$appManager->isEnabledForUser('user_oidc')) {
			$context->getServerContainer()->get(LoggerInterface::class)->warning(
				'oidc_claim_mapping requires user_oidc to be enabled. Claim mapping will not run.'
			);
		}
	}
}
