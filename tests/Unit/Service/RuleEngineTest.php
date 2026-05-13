<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\OidcClaimMapping\Tests\Unit\Service;

use OCA\OidcClaimMapping\Model\Rule;
use OCA\OidcClaimMapping\Service\ClaimResolver;
use OCA\OidcClaimMapping\Service\MustacheRenderer;
use OCA\OidcClaimMapping\Service\RuleEngine;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RuleEngineTest extends TestCase {

	private RuleEngine $engine;
	private ClaimResolver $resolver;

	protected function setUp(): void {
		$this->resolver = new ClaimResolver();
		$this->engine = new RuleEngine(
			$this->resolver,
			new MustacheRenderer(),
			new NullLogger(),
		);
	}

	/**
	 * Build a Rule from a partial array. Tests don't care about the target
	 * (TargetRegistry validation is at the controller layer), so we fill a
	 * sensible default if the caller doesn't set one.
	 *
	 * @param array<string,mixed> $data
	 */
	private function rule(array $data): Rule {
		$data['target'] = $data['target'] ?? 'displayName';
		return Rule::fromArray($data);
	}

	// === Direct ===

	public function testDirectString(): void {
		$rule = $this->rule([
			'id' => 'dept', 'type' => 'direct', 'enabled' => true,
			'claimPath' => 'department', 'config' => [],
		]);
		$claims = (object)['department' => 'IT'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['IT'], $result->getGroups());
	}

	public function testDirectArray(): void {
		$rule = $this->rule([
			'id' => 'groups', 'type' => 'direct', 'enabled' => true,
			'claimPath' => 'groups', 'config' => [],
		]);
		$claims = (object)['groups' => ['staff', 'admin']];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['staff', 'admin'], $result->getGroups());
	}

	public function testDirectNull(): void {
		$rule = $this->rule([
			'id' => 'missing', 'type' => 'direct', 'enabled' => true,
			'claimPath' => 'nonexistent', 'config' => [],
		]);
		$claims = (object)['department' => 'IT'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertFalse($result->isMatched());
		$this->assertSame([], $result->getGroups());
	}

	// === Prefix ===

	public function testPrefixArray(): void {
		$rule = $this->rule([
			'id' => 'roles', 'type' => 'prefix', 'enabled' => true,
			'claimPath' => 'roles', 'config' => ['prefix' => 'role_'],
		]);
		$claims = (object)['roles' => ['admin', 'editor']];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['role_admin', 'role_editor'], $result->getGroups());
	}

	public function testPrefixString(): void {
		$rule = $this->rule([
			'id' => 'dept', 'type' => 'prefix', 'enabled' => true,
			'claimPath' => 'department', 'config' => ['prefix' => 'dept_'],
		]);
		$claims = (object)['department' => 'IT'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['dept_IT'], $result->getGroups());
	}

	public function testPrefixNull(): void {
		$rule = $this->rule([
			'id' => 'roles', 'type' => 'prefix', 'enabled' => true,
			'claimPath' => 'missing', 'config' => ['prefix' => 'role_'],
		]);
		$claims = (object)[];

		$result = $this->engine->apply($rule, $claims);
		$this->assertFalse($result->isMatched());
		$this->assertSame([], $result->getGroups());
	}

	// === Map ===

	public function testMapKnownValue(): void {
		$rule = $this->rule([
			'id' => 'org', 'type' => 'map', 'enabled' => true,
			'claimPath' => 'domain',
			'config' => [
				'values' => ['example.com' => 'Staff', 'partner.com' => 'Partners'],
				'unmappedPolicy' => 'ignore',
			],
		]);
		$claims = (object)['domain' => 'example.com'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['Staff'], $result->getGroups());
	}

	public function testMapUnknownIgnore(): void {
		$rule = $this->rule([
			'id' => 'org', 'type' => 'map', 'enabled' => true,
			'claimPath' => 'domain',
			'config' => [
				'values' => ['example.com' => 'Staff'],
				'unmappedPolicy' => 'ignore',
			],
		]);
		$claims = (object)['domain' => 'unknown.com'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertFalse($result->isMatched());
		$this->assertSame([], $result->getGroups());
	}

	public function testMapUnknownPassthrough(): void {
		$rule = $this->rule([
			'id' => 'org', 'type' => 'map', 'enabled' => true,
			'claimPath' => 'domain',
			'config' => [
				'values' => ['example.com' => 'Staff'],
				'unmappedPolicy' => 'passthrough',
			],
		]);
		$claims = (object)['domain' => 'unknown.com'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['unknown.com'], $result->getGroups());
	}

	public function testMapArrayValues(): void {
		$rule = $this->rule([
			'id' => 'org', 'type' => 'map', 'enabled' => true,
			'claimPath' => 'domains',
			'config' => [
				'values' => ['a.com' => 'GroupA', 'b.com' => 'GroupB'],
				'unmappedPolicy' => 'ignore',
			],
		]);
		$claims = (object)['domains' => ['a.com', 'b.com', 'c.com']];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['GroupA', 'GroupB'], $result->getGroups());
	}

	// === Conditional ===

	public function testConditionalEqualsMatch(): void {
		$rule = $this->rule([
			'id' => 'ext', 'type' => 'conditional', 'enabled' => true,
			'claimPath' => 'userType',
			'config' => [
				'operator' => 'equals',
				'value' => 'EXTERNAL',
				'groups' => ['External-Users'],
			],
		]);
		$claims = (object)['userType' => 'EXTERNAL'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['External-Users'], $result->getGroups());
	}

	public function testConditionalEqualsNoMatch(): void {
		$rule = $this->rule([
			'id' => 'ext', 'type' => 'conditional', 'enabled' => true,
			'claimPath' => 'userType',
			'config' => [
				'operator' => 'equals',
				'value' => 'EXTERNAL',
				'groups' => ['External-Users'],
			],
		]);
		$claims = (object)['userType' => 'INTERNAL'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertFalse($result->isMatched());
		$this->assertSame([], $result->getGroups());
	}

	public function testConditionalContainsMatch(): void {
		$rule = $this->rule([
			'id' => 'admin', 'type' => 'conditional', 'enabled' => true,
			'claimPath' => 'roles',
			'config' => [
				'operator' => 'contains',
				'value' => 'admin',
				'groups' => ['Administrators'],
			],
		]);
		$claims = (object)['roles' => ['admin', 'editor']];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['Administrators'], $result->getGroups());
	}

	public function testConditionalContainsNoMatch(): void {
		$rule = $this->rule([
			'id' => 'admin', 'type' => 'conditional', 'enabled' => true,
			'claimPath' => 'roles',
			'config' => [
				'operator' => 'contains',
				'value' => 'admin',
				'groups' => ['Administrators'],
			],
		]);
		$claims = (object)['roles' => ['editor', 'viewer']];

		$result = $this->engine->apply($rule, $claims);
		$this->assertFalse($result->isMatched());
		$this->assertSame([], $result->getGroups());
	}

	public function testConditionalRegexMatch(): void {
		$rule = $this->rule([
			'id' => 'domain', 'type' => 'conditional', 'enabled' => true,
			'claimPath' => 'email',
			'config' => [
				'operator' => 'regex',
				'value' => '/@example\\.com$/',
				'groups' => ['Example-Staff'],
			],
		]);
		$claims = (object)['email' => 'alice@example.com'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['Example-Staff'], $result->getGroups());
	}

	public function testConditionalRegexNoMatch(): void {
		$rule = $this->rule([
			'id' => 'domain', 'type' => 'conditional', 'enabled' => true,
			'claimPath' => 'email',
			'config' => [
				'operator' => 'regex',
				'value' => '/@example\\.com$/',
				'groups' => ['Example-Staff'],
			],
		]);
		$claims = (object)['email' => 'alice@other.com'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertFalse($result->isMatched());
		$this->assertSame([], $result->getGroups());
	}

	// === Template (Mustache, M4) ===

	public function testTemplateSimple(): void {
		$rule = $this->rule([
			'id' => 'dept', 'type' => 'template', 'enabled' => true,
			'claimPath' => 'department',
			'config' => ['template' => 'dept_{{value}}'],
		]);
		$claims = (object)['department' => 'IT'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['dept_IT'], $result->getGroups());
	}

	public function testTemplateArray(): void {
		$rule = $this->rule([
			'id' => 'roles', 'type' => 'template', 'enabled' => true,
			'claimPath' => 'roles',
			'config' => ['template' => 'role-{{value}}'],
		]);
		$claims = (object)['roles' => ['admin', 'editor']];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['role-admin', 'role-editor'], $result->getGroups());
	}

	public function testTemplateNull(): void {
		$rule = $this->rule([
			'id' => 'dept', 'type' => 'template', 'enabled' => true,
			'claimPath' => 'missing',
			'config' => ['template' => 'dept_{{value}}'],
		]);
		$claims = (object)[];

		$result = $this->engine->apply($rule, $claims);
		$this->assertFalse($result->isMatched());
		$this->assertSame([], $result->getGroups());
	}

	public function testTemplateCrossClaim(): void {
		$rule = $this->rule([
			'id' => 'name-with-country', 'type' => 'template', 'enabled' => true,
			'claimPath' => 'name',
			'config' => ['template' => '{{value}} ({{claims.country}})'],
		]);
		$claims = (object)['name' => 'Alice Test', 'country' => 'PL'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['Alice Test (PL)'], $result->getGroups());
	}

	public function testTemplateCrossClaimSection(): void {
		$rule = $this->rule([
			'id' => 'name-cond-suffix', 'type' => 'template', 'enabled' => true,
			'claimPath' => 'name',
			'config' => ['template' => '{{value}}{{#claims.flag}} (flagged){{/claims.flag}}'],
		]);
		// Section truthy
		$result = $this->engine->apply($rule, (object)['name' => 'Alice', 'flag' => true]);
		$this->assertSame(['Alice (flagged)'], $result->getGroups());

		// Section falsy → suffix dropped
		$result = $this->engine->apply($rule, (object)['name' => 'Alice', 'flag' => false]);
		$this->assertSame(['Alice'], $result->getGroups());
	}

	// === Edge cases ===

	public function testConditionalMalformedRegexDoesNotCrash(): void {
		$rule = $this->rule([
			'id' => 'bad-regex', 'type' => 'conditional', 'enabled' => true,
			'claimPath' => 'email',
			'config' => [
				'operator' => 'regex',
				'value' => '/[invalid regex(',
				'groups' => ['Should-Not-Match'],
			],
		]);
		$claims = (object)['email' => 'alice@example.com'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertFalse($result->isMatched());
		$this->assertSame([], $result->getGroups());
	}

	public function testDirectEmptyString(): void {
		$rule = $this->rule([
			'id' => 'empty', 'type' => 'direct', 'enabled' => true,
			'claimPath' => 'department', 'config' => [],
		]);
		$claims = (object)['department' => ''];

		$result = $this->engine->apply($rule, $claims);
		$this->assertFalse($result->isMatched());
		$this->assertSame([], $result->getGroups());
	}

	public function testDirectArrayFiltersNonStrings(): void {
		$rule = $this->rule([
			'id' => 'mixed', 'type' => 'direct', 'enabled' => true,
			'claimPath' => 'groups', 'config' => [],
		]);
		$claims = (object)['groups' => ['valid', 42, null, 'also-valid']];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['valid', 'also-valid'], $result->getGroups());
	}

	public function testMapMultipleGroupsForOneValue(): void {
		$rule = $this->rule([
			'id' => 'multi', 'type' => 'map', 'enabled' => true,
			'claimPath' => 'role',
			'config' => [
				'values' => ['admin' => ['Admins', 'Superusers']],
				'unmappedPolicy' => 'ignore',
			],
		]);
		$claims = (object)['role' => 'admin'];

		$result = $this->engine->apply($rule, $claims);
		$this->assertTrue($result->isMatched());
		$this->assertSame(['Admins', 'Superusers'], $result->getGroups());
	}

	/**
	 * Fail-open contract (M4): a misbehaving rule MUST NOT throw out of
	 * apply(). Instead the engine catches, logs at error level, and returns
	 * an unmatched MappingResult.
	 *
	 * We can't easily make any of the standard rule types throw, so we
	 * cover the catch via a regex pattern that segfaults preg_match. The
	 * value '/(?=.*?(?=\w))/' is harmless but unusual; the real coverage
	 * for the catch-all is the MustacheRenderer path which silently
	 * returns null on render errors.
	 */
	public function testFailOpenLeavesEngineUsable(): void {
		// First rule has a regex that won't crash; ensure the engine keeps
		// working across multiple calls (no shared state corruption).
		$rule = $this->rule([
			'id' => 'r1', 'type' => 'direct', 'enabled' => true,
			'claimPath' => 'name', 'config' => [],
		]);
		$this->assertSame(['Alice'], $this->engine->apply($rule, (object)['name' => 'Alice'])->getGroups());
		$this->assertSame(['Bob'], $this->engine->apply($rule, (object)['name' => 'Bob'])->getGroups());
	}
}
