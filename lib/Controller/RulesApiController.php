<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Controller;

use OCA\OidcClaimMapping\Model\RuleCollection;
use OCA\OidcClaimMapping\Service\MustacheRenderer;
use OCA\OidcClaimMapping\Service\RuleEngine;
use OCA\OidcClaimMapping\Service\TargetRegistry;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IAppConfig;
use OCP\IRequest;
use Throwable;

class RulesApiController extends OCSController {

	public function __construct(
		IRequest $request,
		private IAppConfig $appConfig,
		private RuleEngine $ruleEngine,
		private TargetRegistry $targetRegistry,
		private MustacheRenderer $mustache,
	) {
		parent::__construct('oidc_claim_mapping', $request);
	}

	/**
	 * Get the current mapping rules.
	 */
	public function index(): DataResponse {
		$json = $this->appConfig->getValueString('oidc_claim_mapping', 'mapping_rules', '');
		$collection = RuleCollection::fromJson($json);

		return new DataResponse([
			'version' => $collection->getVersion(),
			'mode' => $collection->getMode(),
			'rules' => array_map(fn ($r) => $r->toArray(), $collection->getRules()),
		]);
	}

	/**
	 * Save mapping rules.
	 */
	public function update(string $rules): DataResponse {
		$decoded = json_decode($rules, true);
		if (!is_array($decoded)) {
			return new DataResponse(
				['message' => 'Invalid JSON'],
				Http::STATUS_BAD_REQUEST,
			);
		}

		// Validate every rule's `target` against the TargetRegistry BEFORE
		// handing the payload to RuleCollection::fromJson(), which silently
		// drops rules that fail Rule::fromArray() validation. Surfacing the
		// rejection here gives the admin a clear HTTP 400 with a diagnostic
		// message instead of a "rule disappeared" mystery on the next read.
		foreach ($decoded['rules'] ?? [] as $idx => $ruleData) {
			$target = $ruleData['target'] ?? null;
			if (!is_string($target) || $target === '') {
				return new DataResponse(
					['message' => "Rule at index {$idx} is missing the required string field 'target'."],
					Http::STATUS_BAD_REQUEST,
				);
			}
			if ($this->targetRegistry->isForbidden($target)) {
				return new DataResponse(
					['message' => "target='{$target}' is not allowed here. Group mapping lives in the companion oidc_groups_mapping app."],
					Http::STATUS_BAD_REQUEST,
				);
			}
			if (!$this->targetRegistry->isSupported($target)) {
				$supported = implode(', ', $this->targetRegistry->getSupportedTargets());
				return new DataResponse(
					['message' => "Unknown target '{$target}'. Supported targets: {$supported}"],
					Http::STATUS_BAD_REQUEST,
				);
			}

			// Validate Mustache syntax for template rules at save time, so admins
			// catch a typo before the next login instead of finding a silent
			// fail-open in the journal. We compile the template; any syntax
			// error throws and surfaces here as HTTP 400.
			if (($ruleData['type'] ?? null) === 'template') {
				$template = $ruleData['config']['template'] ?? null;
				if (!is_string($template) || $template === '') {
					return new DataResponse(
						['message' => "Rule at index {$idx} is of type 'template' but is missing config.template (string)."],
						Http::STATUS_BAD_REQUEST,
					);
				}
				try {
					$this->mustache->validate($template);
				} catch (Throwable $e) {
					return new DataResponse(
						['message' => "Rule at index {$idx} has an invalid Mustache template: {$e->getMessage()}"],
						Http::STATUS_BAD_REQUEST,
					);
				}
			}
		}

		$collection = RuleCollection::fromJson($rules);
		$canonical = $collection->toJson();

		$this->appConfig->setValueString('oidc_claim_mapping', 'mapping_rules', $canonical);

		return new DataResponse([
			'version' => $collection->getVersion(),
			'mode' => $collection->getMode(),
			'rules' => array_map(fn ($r) => $r->toArray(), $collection->getRules()),
			'rules_count' => count($collection->getRules()),
			'enabled_count' => count($collection->getEnabledRules()),
		]);
	}

	/**
	 * Simulate mapping rules against a sample token.
	 */
	public function simulate(string $token, string $existing = '[]'): DataResponse {
		$claims = json_decode($token);
		if (!is_object($claims)) {
			return new DataResponse(
				['message' => 'Invalid JSON token'],
				Http::STATUS_BAD_REQUEST,
			);
		}

		$existingGroups = json_decode($existing, true);
		if (!is_array($existingGroups)) {
			$existingGroups = [];
		}

		$rulesJson = $this->appConfig->getValueString('oidc_claim_mapping', 'mapping_rules', '');
		$collection = RuleCollection::fromJson($rulesJson);
		$enabledRules = $collection->getEnabledRules();

		$ruleResults = [];
		$producedGroups = [];

		foreach ($enabledRules as $rule) {
			$result = $this->ruleEngine->apply($rule, $claims);
			$ruleResults[] = [
				'ruleId' => $result->getRuleId(),
				'matched' => $result->isMatched(),
				'groups' => $result->getGroups(),
			];
			if ($result->isMatched()) {
				array_push($producedGroups, ...$result->getGroups());
			}
		}

		$mode = $collection->getMode();
		if ($mode === 'replace') {
			$finalGroups = count($producedGroups) === 0
				? $existingGroups
				: array_values(array_unique($producedGroups));
		} else {
			$finalGroups = array_values(array_unique(array_merge($existingGroups, $producedGroups)));
		}

		return new DataResponse([
			'mode' => $mode,
			'ruleResults' => $ruleResults,
			'producedGroups' => array_values(array_unique($producedGroups)),
			'existingGroups' => $existingGroups,
			'finalGroups' => $finalGroups,
		]);
	}
}
