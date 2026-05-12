<!--
  - SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->

<template>
	<div class="rule-editor">
		<h3>{{ isNew ? 'Add rule' : 'Edit rule' }}</h3>

		<div class="form-row">
			<label for="rule-target">Target attribute <span class="required">*</span></label>
			<select id="rule-target" v-model="local.target">
				<option value="" disabled>Choose a target…</option>
				<option v-for="t in availableTargets" :key="t" :value="t">{{ t }}</option>
			</select>
			<p class="form-hint">
				The Nextcloud user attribute this rule writes into. Mirrored from user_oidc 7.4 scalar mappings.
				Group mapping lives in the companion <code>oidc_groups_mapping</code> app.
			</p>
		</div>

		<div class="form-row">
			<label for="rule-provider">Provider scope</label>
			<select id="rule-provider" v-model="local.providerIdentifier">
				<option value="*">* (any provider)</option>
				<option v-for="p in availableProviders"
					:key="p.identifier"
					:value="p.identifier">
					{{ p.identifier }}
				</option>
			</select>
			<p class="form-hint">
				Restrict this rule to a single user_oidc provider (matched via the <code>iss</code> token claim),
				or <code>*</code> to apply on every provider.
			</p>
		</div>

		<div class="form-row">
			<label for="rule-type">Type</label>
			<select id="rule-type" v-model="local.type" @change="onTypeChange">
				<option value="direct">direct</option>
				<option value="prefix">prefix</option>
				<option value="map">map</option>
				<option value="conditional">conditional</option>
				<option value="template">template</option>
			</select>
		</div>

		<div class="form-row">
			<label for="rule-claim">Claim path</label>
			<input id="rule-claim"
				v-model="local.claimPath"
				type="text"
				placeholder="e.g. name, email, extended_attrs.country" />
		</div>

		<div class="form-row">
			<label>
				<input v-model="local.enabled" type="checkbox" />
				Enabled
			</label>
		</div>

		<!-- direct: no extra config -->

		<!-- prefix -->
		<div v-if="local.type === 'prefix'" class="form-row">
			<label for="cfg-prefix">Prefix</label>
			<input id="cfg-prefix" v-model="local.config.prefix" type="text" placeholder="e.g. role_" />
		</div>

		<!-- map -->
		<template v-if="local.type === 'map'">
			<div class="form-row">
				<label for="cfg-unmapped">Unmapped policy</label>
				<select id="cfg-unmapped" v-model="local.config.unmappedPolicy">
					<option value="ignore">ignore</option>
					<option value="passthrough">passthrough</option>
				</select>
			</div>
			<div class="form-row">
				<label>Value mappings</label>
				<div v-for="(val, key) in local.config.values" :key="key" class="mapping-row">
					<input :value="key" type="text" placeholder="claim value" readonly class="mapping-key" />
					<span class="mapping-arrow">&rarr;</span>
					<input :value="val" type="text" placeholder="group name"
						@input="updateMapping(key, $event.target.value)" />
					<button class="action-btn action-btn--danger" @click="removeMapping(key)">
						&times;
					</button>
				</div>
				<div class="mapping-row mapping-row--new">
					<input v-model="newMappingKey" type="text" placeholder="claim value" />
					<span class="mapping-arrow">&rarr;</span>
					<input v-model="newMappingValue" type="text" placeholder="group name" />
					<button class="action-btn" :disabled="!newMappingKey" @click="addMapping">
						Add
					</button>
				</div>
			</div>
		</template>

		<!-- conditional -->
		<template v-if="local.type === 'conditional'">
			<div class="form-row">
				<label for="cfg-operator">Operator</label>
				<select id="cfg-operator" v-model="local.config.operator">
					<option value="equals">equals</option>
					<option value="contains">contains</option>
					<option value="regex">regex</option>
				</select>
			</div>
			<div class="form-row">
				<label for="cfg-value">Value</label>
				<input id="cfg-value" v-model="local.config.value" type="text" placeholder="expected value" />
			</div>
			<div class="form-row">
				<label for="cfg-groups">Groups (comma-separated)</label>
				<input id="cfg-groups"
					:value="(local.config.groups || []).join(', ')"
					type="text"
					placeholder="group1, group2"
					@input="local.config.groups = $event.target.value.split(',').map(s => s.trim()).filter(Boolean)" />
			</div>
		</template>

		<!-- template -->
		<div v-if="local.type === 'template'" class="form-row">
			<label for="cfg-template">Template <span class="form-hint-inline">(Mustache)</span></label>
			<input id="cfg-template"
				v-model="local.config.template"
				type="text"
				:placeholder="templatePlaceholder" />
			<p class="form-hint">
				<code v-text="ex.value" /> renders the claim value.
				<code v-text="ex.claim" /> reads any other claim.
				Sections like <code v-text="ex.section" /> are supported.
			</p>
		</div>

		<div class="editor-actions">
			<button class="primary" :disabled="!canSubmit" @click="onSubmit">
				{{ isNew ? 'Add' : 'Update' }}
			</button>
			<button @click="$emit('cancel')">
				Cancel
			</button>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export default {
	name: 'RuleEditor',
	props: {
		rule: {
			type: Object,
			required: true,
		},
		isNew: {
			type: Boolean,
			default: false,
		},
	},
	data() {
		const seed = JSON.parse(JSON.stringify(this.rule))
		// Backwards-compat: rules created before M2 (or from a CLI dump)
		// may miss `target` / `providerIdentifier`. Default in-editor so
		// the user can fill them before the save round-trip.
		if (!seed.target) {
			seed.target = ''
		}
		if (!seed.providerIdentifier) {
			seed.providerIdentifier = '*'
		}
		return {
			local: seed,
			newMappingKey: '',
			newMappingValue: '',
			availableTargets: [],
			availableProviders: [],
		}
	},
	computed: {
		canSubmit() {
			return !!this.local.target && !!this.local.claimPath
		},
		ex() {
			// Mustache example strings — declared in JS to avoid Vue's
			// double-braces parser confusing them with template bindings.
			return {
				value: '{{value}}',
				claim: '{{claims.path.to.thing}}',
				section: '{{#claims.flag}}…{{/claims.flag}}',
			}
		},
		templatePlaceholder() {
			return 'e.g. {{value}} ({{claims.country}})'
		},
	},
	watch: {
		rule: {
			handler(val) {
				const seed = JSON.parse(JSON.stringify(val))
				if (!seed.target) seed.target = ''
				if (!seed.providerIdentifier) seed.providerIdentifier = '*'
				this.local = seed
			},
			deep: true,
		},
	},
	async mounted() {
		await Promise.all([
			this.fetchTargets(),
			this.fetchProviders(),
		])
	},
	methods: {
		async fetchTargets() {
			try {
				const res = await axios.get(generateOcsUrl('apps/oidc_claim_mapping/api/v1/targets'))
				this.availableTargets = res.data?.ocs?.data?.targets || []
			} catch (e) {
				console.error('[oidc_claim_mapping] Failed to fetch targets', e)
				this.availableTargets = []
			}
		},
		async fetchProviders() {
			try {
				const res = await axios.get(generateOcsUrl('apps/oidc_claim_mapping/api/v1/providers'))
				this.availableProviders = res.data?.ocs?.data?.providers || []
			} catch (e) {
				console.error('[oidc_claim_mapping] Failed to fetch providers', e)
				this.availableProviders = []
			}
		},
		onTypeChange() {
			// Reset config to defaults for the new type
			const defaults = {
				direct: {},
				prefix: { prefix: '' },
				map: { values: {}, unmappedPolicy: 'ignore' },
				conditional: { operator: 'equals', value: '', groups: [] },
				template: { template: '{{value}}' },
			}
			this.local.config = defaults[this.local.type] || {}
		},
		addMapping() {
			if (!this.newMappingKey) {
				return
			}
			if (!this.local.config.values) {
				this.$set(this.local.config, 'values', {})
			}
			this.$set(this.local.config.values, this.newMappingKey, this.newMappingValue)
			this.newMappingKey = ''
			this.newMappingValue = ''
		},
		removeMapping(key) {
			this.$delete(this.local.config.values, key)
		},
		updateMapping(key, value) {
			this.$set(this.local.config.values, key, value)
		},
		onSubmit() {
			this.$emit('save', JSON.parse(JSON.stringify(this.local)))
		},
	},
}
</script>

<style scoped>
.rule-editor {
	border: 2px solid var(--color-primary-element);
	border-radius: var(--border-radius-large, 10px);
	padding: 16px;
	margin-top: 12px;
	background-color: var(--color-main-background);
}

.rule-editor h3 {
	margin-top: 0;
	margin-bottom: 12px;
}

.form-row {
	margin-bottom: 12px;
}

.form-row label {
	display: block;
	font-weight: 600;
	font-size: 13px;
	margin-bottom: 4px;
}

.form-hint {
	margin-top: 4px;
	margin-bottom: 0;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	max-width: 600px;
}

.form-hint-inline {
	font-weight: 400;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-left: 6px;
}

.form-hint code,
.form-row code {
	font-family: var(--font-monospace, monospace);
	background: var(--color-background-dark);
	padding: 1px 4px;
	border-radius: 3px;
	font-size: 11px;
}

.required {
	color: var(--color-error);
}

.form-row input[type="text"],
.form-row select {
	width: 100%;
	max-width: 400px;
	padding: 6px 8px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius, 3px);
	font-size: 14px;
	box-sizing: border-box;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.action-btn {
	color: var(--color-main-text);
}

.form-row input[type="checkbox"] {
	margin-right: 6px;
}

.mapping-row {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 6px;
}

.mapping-row input {
	max-width: 180px !important;
}

.mapping-key {
	background-color: var(--color-background-dark);
}

.mapping-arrow {
	font-size: 16px;
	color: var(--color-text-maxcontrast);
}

.action-btn {
	padding: 4px 10px;
	font-size: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius, 3px);
	background: var(--color-main-background);
	cursor: pointer;
}

.action-btn:hover {
	background: var(--color-background-hover);
}

.action-btn--danger {
	color: var(--color-error);
	border-color: var(--color-error);
}

.editor-actions {
	display: flex;
	gap: 8px;
	margin-top: 16px;
}
</style>
