<template>
	<div class="k-profiles-button">
		<k-button
			:dropdown="true"
			:title="title"
			variant="filled"
			:icon="icon"
			ref="btn"
			size="sm"
			:text="text"
			:theme="theme"
			@click="$refs.menu.toggle()"
		/>

		<k-dropdown-content
			ref="menu"
			alignX="end"
			:anchor="anchor"
			:options="options"
			@close="onClose"
		/>
	</div>
</template>

<script>
export default {
	name: "KProfilesButton",
	props: {
		text:   { String, default: 'Profiles' },
		icon:   { String, default: 'account' },
		theme:  { String, default: 'pink-icon' },
		title:  { String, default: '' },
		items:  { Array,  default: () => [] }
	},

	computed: {
		options() {
			// k-dropdown-content expects standard dropdown option shape:
			// { text, icon, link, target, disabled, click, ... }
			return this.items.map((it) => ({
				text: it.text,
				icon: it.icon,
				disabled: !!it.disabled,
				click: () => this.go(it),
				target: it.target || null,
			}));
		},

    anchor() {
			// k-dropdown expects a real dom node
			const r = this.$refs.btn;
			return r && (r.$el || r);
		}
	},

	mounted() {
		console.log('[ProfilesButton] mounted', this.options);
	},

	methods: {
		toggle() {
			const menu = this.$refs.menu;
			if (!menu) return;

			// ensure we have an anchor before toggling
			if (!this.anchor) return;
			menu.toggle();
		},

		onClose() {
			// console.log('[ProfilesButton] dropdown closed');
		},

		go(item) {
			// console.log('[ProfilesButton] go →', item);
			if (item.disabled) return;
			if (item?.link) window.open(item.link, item.target || "_blank");
		},
	}

}
</script>
