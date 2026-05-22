<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Listener;

use OCA\OidcClaimMapping\Model\Rule;
use OCA\OidcClaimMapping\Model\RuleCollection;
use OCA\OidcClaimMapping\Service\ProviderResolver;
use OCA\OidcClaimMapping\Service\RuleEngine;
use OCA\OidcClaimMapping\Service\TargetRegistry;
use OCA\UserOIDC\Event\AttributeMappedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Handles `AttributeMappedEvent` for scalar user attributes (V1 scope).
 *
 * Workflow:
 *   1. Decode the event attribute name (e.g. `mappingDisplayName`) into a
 *      canonical target (`displayName`) through `TargetRegistry`. Bail out if
 *      the target is not supported by this app — let `user_oidc` keep its
 *      native mapping.
 *   2. Resolve the `iss` claim to a provider identifier through
 *      `ProviderResolver`. When no provider matches we still process rules
 *      bound to `providerIdentifier='*'` (wildcard).
 *   3. Iterate over enabled rules whose `target` matches and apply them in
 *      stored order (first-match-wins, per V1 decision). On the first match
 *      we call `setValue()` + `stopPropagation()` and return.
 *   4. If no rule matches, we silently leave the event alone so `user_oidc`
 *      keeps its native value.
 *
 * The `RuleEngine` historically returns a `string[]` (legacy group surface
 * inherited from `oidc_groups_mapping`). For scalar targets we keep the
 * first element of the result. `MappingResult` will be refactored to carry a
 * proper scalar value at M4 alongside the full Mustache renderer.
 */
class AttributeMappingListener implements IEventListener {

	public function __construct(
		private IAppConfig $appConfig,
		private RuleEngine $ruleEngine,
		private TargetRegistry $targetRegistry,
		private ProviderResolver $providerResolver,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof AttributeMappedEvent)) {
			return;
		}

		$target = $this->targetRegistry->fromEventAttribute($event->getAttribute());
		if ($target === null) {
			return;
		}

		$json = $this->appConfig->getValueString('oidc_claim_mapping', 'mapping_rules', '');
		if ($json === '') {
			return;
		}
		$collection = RuleCollection::fromJson($json);

		$claims = $event->getClaims();
		$iss = is_object($claims) && isset($claims->iss) && is_string($claims->iss) ? $claims->iss : null;
		$providerIdentifier = $this->providerResolver->resolveByIss($iss);
		$providerForMatch = $providerIdentifier ?? Rule::PROVIDER_ANY;

		$rules = array_filter(
			$collection->getEnabledRules(),
			fn (Rule $r) => $r->getTarget() === $target && $r->appliesToProvider($providerForMatch),
		);

		foreach ($rules as $rule) {
			try {
				$result = $this->ruleEngine->apply($rule, $claims);
				// @codeCoverageIgnoreStart
				// Defensive: RuleEngine::apply already wraps in fail-open try/catch.
				// This outer catch protects against future regressions if the
				// engine contract changes; can't be triggered in tests today.
			} catch (Throwable $e) {
				$this->logger->error('Rule {rule} threw while processing target {target}: {msg}', [
					'rule' => $rule->getId(),
					'target' => $target,
					'msg' => $e->getMessage(),
				]);
				continue;
				// @codeCoverageIgnoreEnd
			}

			if (!$result->isMatched()) {
				$this->logger->debug('Rule {rule} skipped (no match) for target {target}', [
					'rule' => $rule->getId(),
					'target' => $target,
				]);
				continue;
			}

			$produced = $result->getGroups();
			// @codeCoverageIgnoreStart
			// Defensive: RuleEngine sets matched=count($groups)>0 so a
			// matched result with empty groups is unreachable in practice.
			// Kept for safety if the engine contract ever changes.
			if (count($produced) === 0) {
				continue;
			}
			// @codeCoverageIgnoreEnd
			$value = (string)$produced[0];

			$this->logger->debug('Rule {rule} matched target {target}, value="{value}", provider={provider}', [
				'rule' => $rule->getId(),
				'target' => $target,
				'value' => $value,
				'provider' => $providerIdentifier ?? '*',
			]);

			$event->setValue($value);
			$event->stopPropagation();
			return;
		}

		$this->logger->debug('No rule matched target {target} (provider={provider})', [
			'target' => $target,
			'provider' => $providerIdentifier ?? '*',
		]);
	}
}
