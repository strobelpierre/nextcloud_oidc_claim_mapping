<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
	'ocs' => [
		['name' => 'RulesApi#index', 'url' => '/api/v1/rules', 'verb' => 'GET'],
		['name' => 'RulesApi#update', 'url' => '/api/v1/rules', 'verb' => 'PUT'],
		['name' => 'RulesApi#simulate', 'url' => '/api/v1/simulate', 'verb' => 'POST'],
		['name' => 'RulesApi#targets', 'url' => '/api/v1/targets', 'verb' => 'GET'],
		['name' => 'RulesApi#providers', 'url' => '/api/v1/providers', 'verb' => 'GET'],
	],
];
