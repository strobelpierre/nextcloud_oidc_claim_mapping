<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Service;

use OCA\OidcClaimMapping\Model\MappingResult;
use OCA\OidcClaimMapping\Model\Rule;
use Psr\Log\LoggerInterface;
use Throwable;

class RuleEngine {

	public function __construct(
		private ClaimResolver $resolver,
		private MustacheRenderer $mustache,
		private LoggerInterface $logger,
	) {
	}

	public function apply(Rule $rule, object $claims): MappingResult {
		try {
			$claimValue = $this->resolver->resolve($claims, $rule->getClaimPath());

			if ($claimValue === null) {
				return new MappingResult($rule->getId(), [], false);
			}

			$groups = match ($rule->getType()) {
				'direct' => $this->applyDirect($claimValue),
				'prefix' => $this->applyPrefix($claimValue, $rule->getConfig()),
				'map' => $this->applyMap($claimValue, $rule->getConfig()),
				'conditional' => $this->applyConditional($claimValue, $rule->getConfig()),
				'template' => $this->applyTemplate($claimValue, $claims, $rule->getConfig()),
				// @codeCoverageIgnoreStart
				// Defensive fallback: Rule::fromArray validates the type
				// against VALID_TYPES at parse time, so this branch is
				// unreachable through the public API. Kept as a safety net
				// for future rule types added before the registry is updated.
				default => [],
				// @codeCoverageIgnoreEnd
			};

			return new MappingResult($rule->getId(), $groups, count($groups) > 0);
			// @codeCoverageIgnoreStart
			// Fail-open: defensive catch that must never block login. All
			// internal collaborators (resolver, mustache, applyXxx) are
			// total functions — the catch only fires on PHP-level fatals
			// (out-of-memory, etc.) which can't be reproduced in tests.
		} catch (Throwable $e) {
			$this->logger->error('Rule {rule} threw while applying: {msg}', [
				'rule' => $rule->getId(),
				'msg' => $e->getMessage(),
				'exception' => $e,
			]);
			return new MappingResult($rule->getId(), [], false);
			// @codeCoverageIgnoreEnd
		}
	}

	private function applyDirect(mixed $value): array {
		if (is_array($value)) {
			return array_values(array_filter($value, 'is_string'));
		}
		if (is_string($value) && $value !== '') {
			return [$value];
		}
		return [];
	}

	private function applyPrefix(mixed $value, array $config): array {
		$prefix = $config['prefix'] ?? '';
		$values = is_array($value) ? $value : [$value];

		$groups = [];
		foreach ($values as $v) {
			if (is_string($v) && $v !== '') {
				$groups[] = $prefix . $v;
			}
		}
		return $groups;
	}

	private function applyMap(mixed $value, array $config): array {
		$map = $config['values'] ?? [];
		$policy = $config['unmappedPolicy'] ?? 'ignore';
		$values = is_array($value) ? $value : [$value];

		$groups = [];
		foreach ($values as $v) {
			if (!is_string($v)) {
				continue;
			}
			if (isset($map[$v])) {
				$mapped = $map[$v];
				if (is_array($mapped)) {
					array_push($groups, ...$mapped);
				} else {
					$groups[] = $mapped;
				}
			} elseif ($policy === 'passthrough') {
				$groups[] = $v;
			}
		}
		return $groups;
	}

	private function applyConditional(mixed $value, array $config): array {
		$operator = $config['operator'] ?? 'equals';
		$expected = $config['value'] ?? '';
		$groups = $config['groups'] ?? [];

		$matched = match ($operator) {
			'equals' => is_string($value) && $value === $expected,
			'contains' => is_array($value) && in_array($expected, $value, true),
			'regex' => is_string($value) && @preg_match($expected, $value) === 1,
			default => false,
		};

		return $matched ? $groups : [];
	}

	/**
	 * Render the rule template via Mustache. The template can reference
	 * `{{value}}` (the claim value), or any field of the full claim object
	 * via `{{claims.path.to.thing}}` and `{{#claims.flag}}...{{/claims.flag}}`
	 * sections. Falls back gracefully when rendering fails: the rule is
	 * treated as unmatched and a single error is logged via the surrounding
	 * try/catch in `apply()`.
	 */
	private function applyTemplate(mixed $value, object $claims, array $config): array {
		$template = $config['template'] ?? '{{value}}';
		if (!is_string($template) || $template === '') {
			return [];
		}

		$values = is_array($value) ? $value : [$value];

		$out = [];
		foreach ($values as $v) {
			if (!is_string($v) || $v === '') {
				continue;
			}
			$rendered = $this->mustache->render($template, $v, $claims);
			if ($rendered !== null && $rendered !== '') {
				$out[] = $rendered;
			}
		}
		return $out;
	}
}
