<!--
  - SPDX-FileCopyrightText: 2026 OIDC Claim Mapping Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->

<template>
	<div class="rule-list">
		<details v-for="group in groupedRules"
			:key="group.target"
			class="rule-group"
			:open="group.target === firstNonEmptyTarget">
			<summary class="rule-group-summary">
				<span class="rule-group-target">{{ group.target }}</span>
				<span class="rule-group-count">
					{{ group.items.length }} rule{{ group.items.length !== 1 ? 's' : '' }}
				</span>
			</summary>
			<div class="rule-group-body">
				<RuleCard v-for="entry in group.items"
					:key="entry.rule.id"
					:rule="entry.rule"
					:index="entry.originalIndex"
					:dragging="dragIndex === entry.originalIndex"
					:drag-over="dragOverIndex === entry.originalIndex"
					@toggle="$emit('toggle', entry.originalIndex)"
					@delete="$emit('delete', entry.originalIndex)"
					@edit="$emit('edit', entry.originalIndex)"
					@dragstart.native="onDragStart(entry.originalIndex, $event)"
					@dragover.native.prevent="onDragOver(entry.originalIndex)"
					@dragleave.native="onDragLeave"
					@drop.native.prevent="onDrop(entry.originalIndex)"
					@dragend.native="onDragEnd" />
			</div>
		</details>
	</div>
</template>

<script>
import RuleCard from './RuleCard.vue'

const UNKNOWN_TARGET = '(no target)'

export default {
	name: 'RuleList',
	components: {
		RuleCard,
	},
	props: {
		rules: {
			type: Array,
			required: true,
		},
	},
	data() {
		return {
			dragIndex: null,
			dragOverIndex: null,
		}
	},
	computed: {
		groupedRules() {
			// Group rules by their `target` while preserving each rule's
			// original index inside `this.rules` so the toggle/delete/edit
			// events still address the canonical (flat) list.
			const groups = new Map()
			this.rules.forEach((rule, originalIndex) => {
				const target = rule.target || UNKNOWN_TARGET
				if (!groups.has(target)) {
					groups.set(target, [])
				}
				groups.get(target).push({ rule, originalIndex })
			})
			return [...groups.entries()]
				.map(([target, items]) => ({ target, items }))
				.sort((a, b) => {
					// Push the "(no target)" bucket to the bottom so admins
					// notice legacy rules that need an explicit target.
					if (a.target === UNKNOWN_TARGET) return 1
					if (b.target === UNKNOWN_TARGET) return -1
					return a.target.localeCompare(b.target)
				})
		},
		firstNonEmptyTarget() {
			// Open the first group by default; if everything is "(no target)",
			// open that one so the admin still sees something.
			const first = this.groupedRules[0]
			return first ? first.target : null
		},
	},
	methods: {
		onDragStart(index, event) {
			this.dragIndex = index
			event.dataTransfer.effectAllowed = 'move'
			event.dataTransfer.setData('text/plain', String(index))
		},
		onDragOver(index) {
			if (this.dragIndex !== null && this.dragIndex !== index) {
				this.dragOverIndex = index
			}
		},
		onDragLeave() {
			this.dragOverIndex = null
		},
		onDrop(index) {
			if (this.dragIndex !== null && this.dragIndex !== index) {
				this.$emit('reorder', { from: this.dragIndex, to: index })
			}
			this.dragIndex = null
			this.dragOverIndex = null
		},
		onDragEnd() {
			this.dragIndex = null
			this.dragOverIndex = null
		},
	},
}
</script>

<style scoped>
.rule-list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.rule-group {
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 10px);
	background: var(--color-main-background);
	overflow: hidden;
}

.rule-group-summary {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 10px 14px;
	cursor: pointer;
	user-select: none;
	background: var(--color-background-hover);
	font-weight: 600;
	list-style: none;
}

.rule-group-summary::-webkit-details-marker {
	display: none;
}

.rule-group-summary::before {
	content: '▸';
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	transition: transform 0.15s ease;
	display: inline-block;
}

.rule-group[open] > .rule-group-summary::before {
	transform: rotate(90deg);
}

.rule-group-target {
	font-family: var(--font-monospace, monospace);
	font-size: 14px;
}

.rule-group-count {
	font-weight: 400;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
	margin-left: auto;
}

.rule-group-body {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 12px;
}
</style>
