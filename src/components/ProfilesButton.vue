<template>
	<div class="k-profiles-button">
		<k-button
			ref="btn"
			:dropdown="true"
			:title="title"
			:text="text"
			:icon="icon"
			:theme="theme"
			variant="filled"
			size="sm"
			@click="toggle"
		/>

		<k-dropdown-content
			ref="menu"
			align-x="end"
			:options="options"
		/>
	</div>
</template>

<script>
export default {
	name: "KProfilesButton",
	props: {
		text: {
			type: String,
			default: "Profiles"
		},
		icon: {
			type: String,
			default: "account"
		},
		theme: {
			type: String,
			default: "pink-icon"
		},
		title: {
			type: String,
			default: ""
		},
		items: {
			type: Array,
			default: () => []
		}
	},

  computed: {
    options() {
      return this.items.map((item) => ({
        text: item.text,
        icon: item.icon,
        link: item.link,
        target: item.target || "_blank",
        disabled: Boolean(item.disabled)
      }));
    }
  },

	methods: {
		toggle() {
			const button = this.$refs.btn;
			const menu = this.$refs.menu;

			if (!button || !menu) {
				return;
			}

			const anchor = button.$el || button;

			menu.toggle(anchor);
		},

		go(item) {
			if (item.disabled || !item.link) {
				return;
			}

			window.open(
				item.link,
				item.target || "_blank",
				item.target === "_self" ? undefined : "noopener,noreferrer"
			);
		},
	}

}
</script>
