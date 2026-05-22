<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Minimal stub of OCP\Util used by AdminSettings tests. The real class lives in
 * vendor/nextcloud/ocp/OCP/Util.php but its addScript() implementation pulls in
 * AppScriptDependency and other globals not available in a unit test harness.
 * Including this file before the real class is requested causes PHP to use this
 * no-op version instead.
 */

namespace OCP;

class Util {
	/** @var array<string, list<string>> */
	public static array $addedScripts = [];

	public static function addScript(string $application, ?string $file = null, string $afterAppId = 'core', bool $prepend = false): void {
		if (!isset(self::$addedScripts[$application])) {
			self::$addedScripts[$application] = [];
		}
		self::$addedScripts[$application][] = (string)$file;
	}

	public static function resetForTests(): void {
		self::$addedScripts = [];
	}
}
