<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Service;

use Mustache_Engine;
use Throwable;

/**
 * Renders Mustache templates for the `template` rule type.
 *
 * HTML escaping is disabled because we render into user attributes
 * (display name, email, etc.), not into HTML. Mustache uses `htmlspecialchars`
 * by default which would mangle quotes and ampersands inside display names.
 *
 * The render context is `{value: <claim value>, claims: <full claim object>}`.
 * Templates can reference:
 *   - `{{value}}` — the value at the rule's `claimPath`
 *   - `{{claims.country}}` — any other claim by dot-notation
 *   - `{{#claims.is_external}}(external){{/claims.is_external}}` — sections
 *
 * `loadTemplate()` is exposed separately so the API layer can validate the
 * Mustache syntax at save time and reject invalid templates with HTTP 400.
 * At runtime, `render()` fails open: it logs and returns `null` so the
 * caller can skip the rule and let other rules or user_oidc keep their
 * native value.
 */
class MustacheRenderer {

	private Mustache_Engine $engine;

	public function __construct() {
		$this->engine = new Mustache_Engine([
			// Disable HTML escaping — we write into user attributes, not HTML.
			'escape' => static fn (string $v): string => $v,
			'cache_lambda_templates' => false,
		]);
	}

	/**
	 * Render a template. Returns null on failure (caller decides whether to
	 * skip the rule, log, or fall through).
	 *
	 * @param mixed $value The value at the rule's claimPath.
	 * @param object|array $claims The full claim object/array.
	 */
	public function render(string $template, mixed $value, object|array $claims): ?string {
		try {
			$context = [
				'value' => $value,
				'claims' => $claims,
			];
			return $this->engine->render($template, $context);
			// @codeCoverageIgnoreStart
			// Defensive: Mustache_Engine::render is total for well-formed
			// templates (validation happens at save via validate()). This
			// catch covers runtime engine-level failures that can't be
			// reproduced once validate() has gated invalid inputs upstream.
		} catch (Throwable) {
			return null;
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * Validate a template by compiling it. Throws if the syntax is invalid.
	 * Use at save time to surface a clear 400 to the admin.
	 */
	public function validate(string $template): void {
		$this->engine->loadTemplate($template);
	}
}
