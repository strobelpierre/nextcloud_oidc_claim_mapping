<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Force-load stubs that must shadow vendor classes (e.g. OCP\Util whose real
// implementation pulls in NC globals not available in the unit-test harness).
// Loading them here registers our class definitions BEFORE the composer
// autoloader has a chance to resolve them from vendor/.
require_once __DIR__ . '/stubs/OCP/Util.php';

// Register stub autoloader for OCP/OCA classes not provided by composer
spl_autoload_register(function (string $class): void {
	$stubDir = __DIR__ . '/stubs/';
	$file = $stubDir . str_replace('\\', '/', $class) . '.php';
	if (file_exists($file)) {
		require_once $file;
	}
});
