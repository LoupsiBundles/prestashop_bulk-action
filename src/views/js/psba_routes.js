/*
 * Ajout dynamique des routes du module au routeur JS du BO.
 * Le composant Router du BO (themes/new-theme/js/components/router.ts)
 * fusionne window.prestashop.customRoutes dans les routes chargées depuis
 * fos_js_routes.json. Ainsi, on peut compléter/patcher les routes côté client
 * sans rebuilder les assets du BO.
 */
(function () {
  try {
    var w = window;
    w.prestashop = w.prestashop || {};
    var existing = w.prestashop.customRoutes || {};

    var custom = {
      // Route: prestashop_bulk_action_make_available
      prestashop_bulk_action_make_available: {
        tokens: [["text", "/modules/prestashop-bulk-action/make-available"]],
        defaults: [],
        requirements: [],
        hosttokens: [],
        methods: ["POST"],
        schemes: []
      },

      // Route: prestashop_bulk_action_make_unavailable
      prestashop_bulk_action_make_unavailable: {
        tokens: [["text", "/modules/prestashop-bulk-action/make-unavailable"]],
        defaults: [],
        requirements: [],
        hosttokens: [],
        methods: ["POST"],
        schemes: []
      },

      // Route: prestashop_bulk_action_backorder_allowed
      prestashop_bulk_action_backorder_allowed: {
        tokens: [["text", "/modules/prestashop-bulk-action/backorder-allowed"]],
        defaults: [],
        requirements: [],
        hosttokens: [],
        methods: ["POST"],
        schemes: []
      },

      // Route: prestashop_bulk_action_backorder_blocked
      prestashop_bulk_action_backorder_blocked: {
        tokens: [["text", "/modules/prestashop-bulk-action/backorder-blocked"]],
        defaults: [],
        requirements: [],
        hosttokens: [],
        methods: ["POST"],
        schemes: []
      },

      // Route: prestashop_bulk_action_backorder_default
      prestashop_bulk_action_backorder_default: {
        tokens: [["text", "/modules/prestashop-bulk-action/backorder-default"]],
        defaults: [],
        requirements: [],
        hosttokens: [],
        methods: ["POST"],
        schemes: []
      }
    };

    // Fusion sans écraser d'éventuels ajouts existants
    w.prestashop.customRoutes = Object.assign({}, existing, custom);
  } catch (e) {
    // ne pas bloquer le BO en cas d'erreur
  }
})();
