import { resolveComponent, createElementBlock, openBlock, createVNode } from "vue";
const _export_sfc = (sfc, props) => {
  const target = sfc.__vccOpts || sfc;
  for (const [key, val] of props) {
    target[key] = val;
  }
  return target;
};
const _sfc_main = {
  name: "KProfilesButton",
  props: {
    text: { type: String, default: "Profiles" },
    icon: { type: String, default: "account" },
    theme: { type: String, default: "pink-icon" },
    title: { type: String, default: "" },
    items: { type: Array, default: () => [] }
  },
  computed: {
    options() {
      return (this.items || []).map((it) => ({
        text: it.text,
        icon: it.icon,
        disabled: !!it.disabled,
        click: () => this.go(it),
        target: it.target || null
      }));
    },
    anchor() {
      const r = this.$refs.btn;
      return r && (r.$el || r);
    }
  },
  mounted() {
    console.log("[ProfilesButton] mounted", this.options);
  },
  methods: {
    toggle() {
      const menu = this.$refs.menu;
      if (!menu) return;
      if (!this.anchor) return;
      menu.toggle();
    },
    onClose() {
    },
    go(item) {
      if (item?.disabled) return;
      if (item?.link) window.open(item.link, item.target || "_blank");
    }
  }
};
const _hoisted_1 = { class: "k-profiles-button" };
function _sfc_render(_ctx, _cache, $props, $setup, $data, $options) {
  const _component_k_button = resolveComponent("k-button");
  const _component_k_dropdown_content = resolveComponent("k-dropdown-content");
  return openBlock(), createElementBlock("div", _hoisted_1, [
    createVNode(_component_k_button, {
      dropdown: true,
      title: $props.title,
      variant: "filled",
      icon: $props.icon,
      ref: "btn",
      size: "sm",
      text: $props.text,
      theme: $props.theme,
      onClick: _cache[0] || (_cache[0] = ($event) => _ctx.$refs.menu.toggle())
    }, null, 8, ["title", "icon", "text", "theme"]),
    createVNode(_component_k_dropdown_content, {
      ref: "menu",
      alignX: "end",
      anchor: $options.anchor,
      options: $options.options,
      onClose: $options.onClose
    }, null, 8, ["anchor", "options", "onClose"])
  ]);
}
const ProfilesButton = /* @__PURE__ */ _export_sfc(_sfc_main, [["render", _sfc_render]]);
panel.plugin("scottboms/promote-button", {
  components: {
    "k-profiles-button": ProfilesButton
  }
});
