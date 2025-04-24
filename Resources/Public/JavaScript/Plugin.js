(() => {
  var __create = Object.create;
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __getProtoOf = Object.getPrototypeOf;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __esm = (fn, res) => function __init() {
    return fn && (res = (0, fn[__getOwnPropNames(fn)[0]])(fn = 0)), res;
  };
  var __commonJS = (cb, mod) => function __require() {
    return mod || (0, cb[__getOwnPropNames(cb)[0]])((mod = { exports: {} }).exports, mod), mod.exports;
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(
    // If the importer is in node compatibility mode or this is not an ESM
    // file that has been converted to a CommonJS file using a Babel-
    // compatible transform (i.e. "__esModule" has not been set), then set
    // "default" to the CommonJS "module.exports" for node compatibility.
    isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", { value: mod, enumerable: true }) : target,
    mod
  ));
  var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

  // ../../node_modules/@neos-project/neos-ui-extensibility/dist/manifest.js
  var init_manifest = __esm({
    "../../node_modules/@neos-project/neos-ui-extensibility/dist/manifest.js"() {
    }
  });

  // ../../node_modules/@neos-project/neos-ui-extensibility/dist/createConsumerApi.js
  var init_createConsumerApi = __esm({
    "../../node_modules/@neos-project/neos-ui-extensibility/dist/createConsumerApi.js"() {
      init_manifest();
    }
  });

  // ../../node_modules/@neos-project/neos-ui-extensibility/dist/readFromConsumerApi.js
  function readFromConsumerApi(key) {
    return (...args) => {
      if (window["@Neos:HostPluginAPI"] && window["@Neos:HostPluginAPI"][`@${key}`]) {
        return window["@Neos:HostPluginAPI"][`@${key}`](...args);
      }
      throw new Error("You are trying to read from a consumer api that hasn't been initialized yet!");
    };
  }
  var init_readFromConsumerApi = __esm({
    "../../node_modules/@neos-project/neos-ui-extensibility/dist/readFromConsumerApi.js"() {
    }
  });

  // ../../node_modules/@neos-project/neos-ui-extensibility/dist/registry/AbstractRegistry.js
  var init_AbstractRegistry = __esm({
    "../../node_modules/@neos-project/neos-ui-extensibility/dist/registry/AbstractRegistry.js"() {
    }
  });

  // ../../node_modules/@neos-project/positional-array-sorter/dist/positionalArraySorter.js
  var init_positionalArraySorter = __esm({
    "../../node_modules/@neos-project/positional-array-sorter/dist/positionalArraySorter.js"() {
    }
  });

  // ../../node_modules/@neos-project/neos-ui-extensibility/dist/registry/SynchronousRegistry.js
  var init_SynchronousRegistry = __esm({
    "../../node_modules/@neos-project/neos-ui-extensibility/dist/registry/SynchronousRegistry.js"() {
      init_AbstractRegistry();
      init_positionalArraySorter();
    }
  });

  // ../../node_modules/@neos-project/neos-ui-extensibility/dist/registry/SynchronousMetaRegistry.js
  var init_SynchronousMetaRegistry = __esm({
    "../../node_modules/@neos-project/neos-ui-extensibility/dist/registry/SynchronousMetaRegistry.js"() {
      init_SynchronousRegistry();
    }
  });

  // ../../node_modules/@neos-project/neos-ui-extensibility/dist/registry/index.js
  var init_registry = __esm({
    "../../node_modules/@neos-project/neos-ui-extensibility/dist/registry/index.js"() {
      init_SynchronousRegistry();
      init_SynchronousMetaRegistry();
    }
  });

  // ../../node_modules/@neos-project/neos-ui-extensibility/dist/index.js
  var dist_default;
  var init_dist = __esm({
    "../../node_modules/@neos-project/neos-ui-extensibility/dist/index.js"() {
      init_createConsumerApi();
      init_readFromConsumerApi();
      init_registry();
      dist_default = readFromConsumerApi("manifest");
    }
  });

  // ../../node_modules/@neos-project/neos-ui-extensibility/dist/shims/vendor/react/index.js
  var require_react = __commonJS({
    "../../node_modules/@neos-project/neos-ui-extensibility/dist/shims/vendor/react/index.js"(exports, module) {
      init_readFromConsumerApi();
      module.exports = readFromConsumerApi("vendor")().React;
    }
  });

  // ../rrule-inspector-editor/src/components/test.tsx
  var React, TestComponent;
  var init_test = __esm({
    "../rrule-inspector-editor/src/components/test.tsx"() {
      React = __toESM(require_react());
      TestComponent = () => {
        return /* @__PURE__ */ React.createElement("div", null, "Test Component RRule Editor");
      };
    }
  });

  // ../rrule-inspector-editor/src/index.tsx
  function registerRRulEditor(globalRegistry) {
    const inspectorRegistry = globalRegistry.get("inspector");
    if (!inspectorRegistry) {
      console.warn("[Sitegeist.Groundhogday.RRuleEditor]: Could not find inspector registry.");
      console.warn("[Sitegeist.Groundhogday.RRuleEditor]: Skipping registration of RRuleEditor...");
      return;
    }
    const editorsRegistry = inspectorRegistry.get("editors");
    if (!editorsRegistry) {
      console.warn("[Sitegeist.Groundhogday.RRuleEditor]: Could not find inspector editors registry.");
      console.warn("[Sitegeist.Groundhogday.RRuleEditor]: Skipping registration of RRuleEditor...");
      return;
    }
    editorsRegistry.set("Sitegeist.Groundhogday/Inspector/Editors/RRuleEditor", {
      component: () => /* @__PURE__ */ React2.createElement(TestComponent, null)
    });
  }
  var React2;
  var init_src = __esm({
    "../rrule-inspector-editor/src/index.tsx"() {
      React2 = __toESM(require_react());
      init_test();
    }
  });

  // src/manifest.js
  var manifest_exports = {};
  var init_manifest2 = __esm({
    "src/manifest.js"() {
      init_dist();
      init_src();
      dist_default("@sitegeist/groundhogday", {}, (globalRegistry) => {
        registerRRulEditor(globalRegistry);
      });
    }
  });

  // src/index.js
  init_manifest2();
})();
//# sourceMappingURL=Plugin.js.map
