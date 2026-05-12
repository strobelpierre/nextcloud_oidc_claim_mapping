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
				default => [],
			};

			return new MappingResult($rule->getId(), $groups, count($groups) > 0);
		} catch (Throwable $e) {
			// Fail-open: a misbehaving rule MUST NOT block the login. We log
			// the error and return an unmatched result so the caller (the
			// listener or simulator) moves on to the next rule.
			$this->logger->error('Rule {rule} threw while applying: {msg}', [
				'rule' => $rule->getId(),
				'msg' => $e->getMessage(),
				'exception' => $e,
			]);
			return new MappingResult($rule->getId(), [], false);
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
