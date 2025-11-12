<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Autoloader PSR-4 minimal pour le module (fallback quand vendor/autoload.php n'est pas présent)
// Ceci garantit que nos classes namespacées (ex: Controller\Admin\BulkActionController, Utils\SelectionExtractor)
// sont chargeables par Symfony/PrestaShop, évitant ainsi des problèmes de résolution de routes.
spl_autoload_register(function ($class) {
    $prefix = 'PrestaShop\\Module\\PrestashopBulkAction\\';
    $len = strlen($prefix);
    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len); // e.g. 'Controller\Admin\BulkActionController' or 'Utils\SelectionExtractor'
    $relativePath = str_replace('\\', '/', $relative) . '.php';

    // Chemins potentiels où peuvent se trouver les classes du module
    $candidates = [
        // Structure PSR-4 classique sous la racine du module
        __DIR__ . '/' . $relativePath,
        // Contrôleurs admin historiques du module
        __DIR__ . '/controllers/admin/' . basename($relativePath),
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\AjaxBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;

class prestashop_bulk_action extends Module
{
    /**
     * Vide le cache Symfony (tolérant) afin de recharger les routes des modules.
     */
    private function clearSymfonyCache(): void
    {
        try {
            if (defined('_PS_ROOT_DIR_')) {
                $console = rtrim(_PS_ROOT_DIR_, '/').'/bin/console';
                if (is_file($console)) {
                    $php = PHP_BINARY ?: 'php';
                    // On tente le cache:clear en dev puis en prod, sans bloquer.
                    @exec(escapeshellcmd($php).' '.escapeshellarg($console).' cache:clear --no-warmup', $o, $c1);
                    @exec(escapeshellcmd($php).' '.escapeshellarg($console).' cache:clear --no-warmup --env=prod', $o2, $c2);
                }
            }
        } catch (\Throwable $e) {
            // silencieux
        }
    }
    /**
     * Déclenche, si possible, le dump des routes JS (FOSJsRouting) afin que le BO
     * connaisse immédiatement les routes exposées par le module.
     * Tolérant: n'échoue jamais l'installation/activation.
     */
    private function dumpFosJsRoutes(): void
    {
        try {
            if (!defined('_PS_ADMIN_DIR_')) {
                return;
            }

            $target = rtrim(_PS_ADMIN_DIR_, '/').'/themes/new-theme/js/fos_js_routes.json';
            $dir = dirname($target);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            // 1) Via l'application Symfony si disponible
            if (class_exists('Symfony\\Bundle\\FrameworkBundle\\Console\\Application')
                && class_exists('Symfony\\Component\\Console\\Input\\ArrayInput')
                && class_exists('Symfony\\Component\\Console\\Output\\BufferedOutput')
                && class_exists('Adapter\\SymfonyContainer')
            ) {
                $container = \Adapter\SymfonyContainer::getInstance();
                if ($container && $container->has('kernel')) {
                    $kernel = $container->get('kernel');
                    if ($kernel) {
                        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
                        $application->setAutoExit(false);

                        $input = new \Symfony\Component\Console\Input\ArrayInput([
                            'command' => 'fos:js-routing:dump',
                            '--format' => 'json',
                            '--target' => $target,
                        ]);
                        $output = new \Symfony\Component\Console\Output\BufferedOutput();
                        $application->run($input, $output);

                        if (is_file($target) && filesize($target) > 10) {
                            return;
                        }
                    }
                }
            }

            // 2) Fallback: exécuter le binaire console
            if (defined('_PS_ROOT_DIR_')) {
                $console = rtrim(_PS_ROOT_DIR_, '/').'/bin/console';
                if (is_file($console)) {
                    $php = PHP_BINARY ?: 'php';
                    $cmd = escapeshellcmd($php).' '.escapeshellarg($console)
                        .' fos:js-routing:dump --format=json --target='
                        .escapeshellarg($target).' --env=prod';
                    @exec($cmd, $ignoredOutput, $code);
                }
            }
        } catch (\Throwable $e) {
            @error_log('[prestashop_bulk_action] dumpFosJsRoutes error: '.$e->getMessage());
        }
    }
    /**
     * Expose explicitement nos routes de module au routeur JS (FOSJsRouting) côté Back Office.
     * Certaines configurations/versions n’exportent pas automatiquement les routes de modules
     * même avec options: { expose: true }. Ce hook permet de forcer l’ajout par nom de route.
     *
     * Signature tolérante: selon les versions, $params peut contenir différentes clés.
     * Nous essayons plusieurs conventions sans casser si non présentes.
     *
     * @param array $params
     * @return void
     */
    public function hookActionBuildJsRoutes(array $params)
    {
        // Compatibilité multi‑versions:
        //  - Certaines versions attendent un RETOUR d'un tableau de noms de routes
        //  - D'autres passent un tableau par référence dans $params['routes'] à compléter
        // On gère les deux sans provoquer d'exception si la structure diffère.
        $ourRoutes = [
            'prestashop_bulk_action_make_available',
            'prestashop_bulk_action_make_unavailable',
            // Nouvelles routes Backorder
            'prestashop_bulk_action_backorder_allowed',
            'prestashop_bulk_action_backorder_blocked',
            'prestashop_bulk_action_backorder_default',
        ];

        try {
            if (isset($params['routes']) && is_array($params['routes'])) {
                // Éviter les doublons
                foreach ($ourRoutes as $r) {
                    if (!in_array($r, $params['routes'], true)) {
                        $params['routes'][] = $r;
                    }
                }
                // Selon cette variante du hook, il n'est pas nécessaire de retourner quelque chose
                // mais retourner le tableau reste inoffensif.
                return $params['routes'];
            }
        } catch (\Throwable $e) {
            // Ne pas casser le BO si la structure du hook diffère
        }

        // Fallback: retourner notre liste (variante de contrat utilisée par d'autres versions)
        return $ourRoutes;
    }
    public function __construct()
    {
        $this->name = 'prestashop_bulk_action';
        $this->tab = 'administration';
        $this->version = '0.1.1';
        $this->author = 'prestashop-bulk-action';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Bulk actions produits');
        $this->description = $this->l('Ajoute des actions groupées sur la grille des produits : « Rendre disponible à la vente » et « Rendre indisponible à la vente ».');
    }

    public function install()
    {
        $ok = parent::install()
            && $this->registerHook('actionProductGridDefinitionModifier')
            && $this->registerHook('actionProductGridDataModifier')
            // Pour FOSJsRouting: expose explicitement nos routes côté BO
            && $this->registerHook('actionBuildJsRoutes')
            // Injection d'un JS léger qui ajoute nos routes au routeur côté BO
            && $this->registerHook('actionAdminControllerSetMedia');

        // Tentative non bloquante: déclencher le dump des routes JS pour que
        // Router.generate() connaisse immédiatement nos routes exposées
        if ($ok) {
            $this->dumpFosJsRoutes();
            $this->clearSymfonyCache();
        }

        return $ok;
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Ensure enable action succeeds without side effects.
     */
    public function enable($force_all = false)
    {
        $enabled = parent::enable($force_all);
        // S'assurer que les nouveaux hooks ajoutés après l'installation sont bien enregistrés
        // même si le module a été mis à jour sans réinstallation.
        if ($enabled) {
            try {
                $this->registerHook('actionProductGridDefinitionModifier');
                $this->registerHook('actionProductGridDataModifier');
                $this->registerHook('actionBuildJsRoutes');
                $this->registerHook('actionAdminControllerSetMedia');
                // Après activation (ou mise à jour), on (re)génère le fichier des routes JS
                $this->dumpFosJsRoutes();
                // Et on purge le cache Symfony pour recharger les routes du module
                $this->clearSymfonyCache();
            } catch (\Throwable $e) {
                // Ne pas bloquer l'activation si l'enregistrement échoue
            }
        }
        return $enabled;
    }

    /**
     * Ensure disable action succeeds without side effects.
     */
    public function disable($force_all = false)
    {
        return parent::disable($force_all);
    }

    /**
     * Add a new bulk action to the products grid.
     *
     * @param array $params
     */
    public function hookActionProductGridDefinitionModifier(array $params)
    {
        try {
            if (!isset($params['definition'])) {
                return;
            }

            $definition = $params['definition'];
            $shopId = 0;
            try {
                $ctx = \Context::getContext();
                if ($ctx && isset($ctx->shop) && $ctx->shop) {
                    $shopId = (int) $ctx->shop->id;
                }
            } catch (\Throwable $e) {
                // ignore, keep default 0
            }

            // 1) Ajuster les colonnes: retirer « Montant HT » et ajouter « Achetable » et « Sur commande »
            if (method_exists($definition, 'getColumns') && method_exists($definition, 'setColumns')) {
                $columns = $definition->getColumns();
                // Supprime la colonne « Price (tax excl.) » dont l'ID est 'final_price_tax_excluded'
                try {
                    $columns->remove('final_price_tax_excluded');
                } catch (\Throwable $e) {
                    // tolérant si la colonne n'existe pas
                }
                // Ajoute une colonne « Achetable » (lecture seule) après la colonne 'category'
                try {
                    $columns->addAfter('category', (new DataColumn('achetable'))
                        ->setName($this->l('Achetable'))
                        ->setOptions([
                            // champ fourni par le hook data ci‑dessous
                            'field' => 'achetable_label',
                            'clickable' => false,
                        ]));
                } catch (\Throwable $e) {
                    // si l'insertion « after category » échoue, on tente un ajout simple
                    try {
                        $columns->add((new DataColumn('achetable'))
                            ->setName($this->l('Achetable'))
                            ->setOptions([
                                'field' => 'achetable_label',
                                'clickable' => false,
                            ]));
                    } catch (\Throwable $e2) {
                        // silencieux
                    }
                }
                // Ajoute une colonne « Sur commande » (lecture seule) après « Achetable »
                try {
                    $columns->addAfter('achetable', (new DataColumn('sur_commande'))
                        ->setName($this->l('Sur commande'))
                        ->setOptions([
                            'field' => 'sur_commande_label',
                            'clickable' => false,
                        ]));
                } catch (\Throwable $e) {
                    // fallback: ajout simple en fin de liste
                    try {
                        $columns->add((new DataColumn('sur_commande'))
                            ->setName($this->l('Sur commande'))
                            ->setOptions([
                                'field' => 'sur_commande_label',
                                'clickable' => false,
                            ]));
                    } catch (\Throwable $e2) {
                        // silencieux
                    }
                }
                $definition->setColumns($columns);
            }

            // 2) Nettoyer le filtre associé à la colonne supprimée s'il existe
            if (method_exists($definition, 'getFilters') && method_exists($definition, 'setFilters')) {
                try {
                    $filters = $definition->getFilters();
                    if ($filters && method_exists($filters, 'remove')) {
                        $filters->remove('final_price_tax_excluded');
                        $definition->setFilters($filters);
                    }
                } catch (\Throwable $e) {
                    // silencieux
                }
            }

            // 3) Conserver/ajouter nos actions de masse existantes
            if (method_exists($definition, 'getBulkActions') && method_exists($definition, 'setBulkActions')) {
                $bulkActions = $definition->getBulkActions();

                $addAjaxActions = function () use ($bulkActions, $shopId) {
                    // Actions AJAX standard (préférées)
                    $bulkActions->add((new AjaxBulkAction('psba_make_available_ajax'))
                        ->setName($this->l('Rendre disponible à la vente'))
                        ->setOptions([
                            'class' => '',
                            'ajax_route' => 'prestashop_bulk_action_make_available',
                            // Aligne le rendu sur les autres actions core: inclut shopId
                            'route_params' => ['shopId' => $shopId],
                            'request_param_name' => 'product_bulk',
                            'bulk_chunk_size' => 10,
                            'reload_after_bulk' => true,
                            'confirm_bulk_action' => true,
                            'modal_confirm_title' => $this->l('Rendre disponible à la vente'),
                            'modal_cancel' => $this->l('Annuler'),
                            'modal_progress_title' => $this->l('Rendre %total% produits disponibles'),
                            'modal_progress_message' => $this->l('Traitement %done% / %total% produits'),
                            'modal_close' => $this->l('Fermer'),
                            'modal_stop_processing' => $this->l('Arrêter le traitement'),
                            'modal_errors_message' => $this->l('%error_count% erreurs se sont produites. Vous pouvez télécharger les logs pour vous y référer ultérieurement.'),
                            'modal_back_to_processing' => $this->l('Reprendre le traitement'),
                            'modal_download_error_log' => $this->l("Télécharger le rapport d'erreur"),
                            'modal_view_error_log' => $this->l('Afficher %error_count% rapports d\'erreur '),
                            'modal_error_title' => $this->l("Journal d'erreurs"),
                        ])
                    );

                    $bulkActions->add((new AjaxBulkAction('psba_make_unavailable_ajax'))
                        ->setName($this->l('Rendre indisponible à la vente'))
                        ->setOptions([
                            'class' => '',
                            'ajax_route' => 'prestashop_bulk_action_make_unavailable',
                            // Aligne le rendu sur les autres actions core: inclut shopId
                            'route_params' => ['shopId' => $shopId],
                            'request_param_name' => 'product_bulk',
                            'bulk_chunk_size' => 10,
                            'reload_after_bulk' => true,
                            'confirm_bulk_action' => true,
                            'modal_confirm_title' => $this->l('Rendre indisponible à la vente'),
                            'modal_cancel' => $this->l('Annuler'),
                            'modal_progress_title' => $this->l('Rendre %total% produits indisponibles'),
                            'modal_progress_message' => $this->l('Traitement %done% / %total% produits'),
                            'modal_close' => $this->l('Fermer'),
                            'modal_stop_processing' => $this->l('Arrêter le traitement'),
                            'modal_errors_message' => $this->l('%error_count% erreurs se sont produites. Vous pouvez télécharger les logs pour vous y référer ultérieurement.'),
                            'modal_back_to_processing' => $this->l('Reprendre le traitement'),
                            'modal_download_error_log' => $this->l("Télécharger le rapport d'erreur"),
                            'modal_view_error_log' => $this->l('Afficher %error_count% rapports d\'erreur '),
                            'modal_error_title' => $this->l("Journal d'erreurs"),
                        ])
                    );

                    // --- Nouvelles actions: Achat sur commande (backorder)
                    $bulkActions->add((new AjaxBulkAction('psba_backorder_allowed_ajax'))
                        ->setName($this->l('Achat sur commande autorisé'))
                        ->setOptions([
                            'class' => '',
                            'ajax_route' => 'prestashop_bulk_action_backorder_allowed',
                            'route_params' => ['shopId' => $shopId],
                            'request_param_name' => 'product_bulk',
                            'bulk_chunk_size' => 10,
                            'reload_after_bulk' => true,
                            'confirm_bulk_action' => true,
                            'modal_confirm_title' => $this->l('Autoriser l’achat sur commande'),
                            'modal_cancel' => $this->l('Annuler'),
                            'modal_progress_title' => $this->l('Autoriser l’achat sur commande pour %total% produits'),
                            'modal_progress_message' => $this->l('Traitement %done% / %total% produits'),
                            'modal_close' => $this->l('Fermer'),
                            'modal_stop_processing' => $this->l('Arrêter le traitement'),
                            'modal_errors_message' => $this->l('%error_count% erreurs se sont produites. Vous pouvez télécharger les logs pour vous y référer ultérieurement.'),
                            'modal_back_to_processing' => $this->l('Reprendre le traitement'),
                            'modal_download_error_log' => $this->l("Télécharger le rapport d'erreur"),
                            'modal_view_error_log' => $this->l('Afficher %error_count% rapports d\'erreur '),
                            'modal_error_title' => $this->l("Journal d'erreurs"),
                        ])
                    );

                    $bulkActions->add((new AjaxBulkAction('psba_backorder_blocked_ajax'))
                        ->setName($this->l('Achat sur commande bloqué'))
                        ->setOptions([
                            'class' => '',
                            'ajax_route' => 'prestashop_bulk_action_backorder_blocked',
                            'route_params' => ['shopId' => $shopId],
                            'request_param_name' => 'product_bulk',
                            'bulk_chunk_size' => 10,
                            'reload_after_bulk' => true,
                            'confirm_bulk_action' => true,
                            'modal_confirm_title' => $this->l('Bloquer l’achat sur commande'),
                            'modal_cancel' => $this->l('Annuler'),
                            'modal_progress_title' => $this->l('Bloquer l’achat sur commande pour %total% produits'),
                            'modal_progress_message' => $this->l('Traitement %done% / %total% produits'),
                            'modal_close' => $this->l('Fermer'),
                            'modal_stop_processing' => $this->l('Arrêter le traitement'),
                            'modal_errors_message' => $this->l('%error_count% erreurs se sont produites. Vous pouvez télécharger les logs pour vous y référer ultérieurement.'),
                            'modal_back_to_processing' => $this->l('Reprendre le traitement'),
                            'modal_download_error_log' => $this->l("Télécharger le rapport d'erreur"),
                            'modal_view_error_log' => $this->l('Afficher %error_count% rapports d\'erreur '),
                            'modal_error_title' => $this->l("Journal d'erreurs"),
                        ])
                    );

                    $bulkActions->add((new AjaxBulkAction('psba_backorder_default_ajax'))
                        ->setName($this->l('Achat sur commande (défaut boutique)'))
                        ->setOptions([
                            'class' => '',
                            'ajax_route' => 'prestashop_bulk_action_backorder_default',
                            'route_params' => ['shopId' => $shopId],
                            'request_param_name' => 'product_bulk',
                            'bulk_chunk_size' => 10,
                            'reload_after_bulk' => true,
                            'confirm_bulk_action' => true,
                            'modal_confirm_title' => $this->l('Revenir au réglage par défaut de la boutique'),
                            'modal_cancel' => $this->l('Annuler'),
                            'modal_progress_title' => $this->l('Appliquer le réglage par défaut à %total% produits'),
                            'modal_progress_message' => $this->l('Traitement %done% / %total% produits'),
                            'modal_close' => $this->l('Fermer'),
                            'modal_stop_processing' => $this->l('Arrêter le traitement'),
                            'modal_errors_message' => $this->l('%error_count% erreurs se sont produites. Vous pouvez télécharger les logs pour vous y référer ultérieurement.'),
                            'modal_back_to_processing' => $this->l('Reprendre le traitement'),
                            'modal_download_error_log' => $this->l("Télécharger le rapport d'erreur"),
                            'modal_view_error_log' => $this->l('Afficher %error_count% rapports d\'erreur '),
                            'modal_error_title' => $this->l("Journal d'erreurs"),
                        ])
                    );
                };

                $addSubmitFallback = function () use ($bulkActions, $shopId) {
                    // Fallback minimal pour garantir l'affichage des actions si AjaxBulkAction indisponible
                    $bulkActions->add((new SubmitBulkAction('psba_make_available_submit'))
                        ->setName($this->l('Rendre disponible à la vente'))
                        ->setOptions([
                            'submit_route' => 'prestashop_bulk_action_make_available',
                            'route_params' => ['shopId' => $shopId],
                            'submit_method' => 'POST',
                        ])
                    );

                    $bulkActions->add((new SubmitBulkAction('psba_make_unavailable_submit'))
                        ->setName($this->l('Rendre indisponible à la vente'))
                        ->setOptions([
                            'submit_route' => 'prestashop_bulk_action_make_unavailable',
                            'route_params' => ['shopId' => $shopId],
                            'submit_method' => 'POST',
                        ])
                    );

                    // Fallback submit pour les actions d'achat sur commande
                    $bulkActions->add((new SubmitBulkAction('psba_backorder_allowed_submit'))
                        ->setName($this->l('Achat sur commande autorisé'))
                        ->setOptions([
                            'submit_route' => 'prestashop_bulk_action_backorder_allowed',
                            'route_params' => ['shopId' => $shopId],
                            'submit_method' => 'POST',
                        ])
                    );

                    $bulkActions->add((new SubmitBulkAction('psba_backorder_blocked_submit'))
                        ->setName($this->l('Achat sur commande bloqué'))
                        ->setOptions([
                            'submit_route' => 'prestashop_bulk_action_backorder_blocked',
                            'route_params' => ['shopId' => $shopId],
                            'submit_method' => 'POST',
                        ])
                    );

                    $bulkActions->add((new SubmitBulkAction('psba_backorder_default_submit'))
                        ->setName($this->l('Achat sur commande (défaut boutique)'))
                        ->setOptions([
                            'submit_route' => 'prestashop_bulk_action_backorder_default',
                            'route_params' => ['shopId' => $shopId],
                            'submit_method' => 'POST',
                        ])
                    );
                };

                try {
                    if (class_exists(AjaxBulkAction::class)) {
                        $addAjaxActions();
                    } else {
                        $addSubmitFallback();
                    }
                } catch (\Throwable $e) {
                    // Si l'ajout d'actions AJAX échoue (mauvaise option, version différente, etc.),
                    // on ajoute un fallback en submit pour éviter la disparition des actions.
                    $addSubmitFallback();
                }

                $definition->setBulkActions($bulkActions);
            }
        } catch (\Throwable $e) {
            // fail silent to avoid breaking back office if something changes
        }
    }

    /**
     * Enrichit les données de la grille Produits pour fournir le libellé « Achetable » (Oui/Non).
     *
     * @param array $params ['data' => GridData]
     */
    public function hookActionProductGridDataModifier(array $params)
    {
        try {
            // Trace légère en mode dev pour diagnostiquer l’exécution du hook
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                @error_log('[prestashop_bulk_action] hookActionProductGridDataModifier: entered');
            }
            if (!isset($params['data']) || !($params['data'] instanceof GridData)) {
                return;
            }

            /** @var GridData $data */
            $data = $params['data'];
            $records = $data->getRecords();

            // Extraire les lignes et les id_product, préparer des valeurs par défaut
            $ids = [];
            $rows = [];
            $rawRows = method_exists($records, 'all') ? $records->all() : (array) $records;
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                @error_log('[prestashop_bulk_action] data hook: initial rows count=' . (is_array($rawRows) ? count($rawRows) : 0));
            }
            foreach ($rawRows as $idx => $row) {
                if (is_array($row)) {
                    $rows[$idx] = $row;
                    if (isset($row['id_product'])) {
                        $ids[] = (int) $row['id_product'];
                    }
                } elseif (is_object($row)) {
                    if (isset($row->id_product)) {
                        $ids[] = (int) $row->id_product;
                    }
                    $rows[$idx] = (array) $row;
                } else {
                    // Ligne inattendue: forcer tableau
                    $rows[$idx] = (array) $row;
                }
                // Valeurs par défaut pour éviter l'erreur « Key ... does not exist »
                if (!isset($rows[$idx]['available_for_order'])) {
                    $rows[$idx]['available_for_order'] = 0;
                }
                if (!isset($rows[$idx]['achetable_label'])) {
                    $rows[$idx]['achetable_label'] = $this->l('Non');
                }
            }
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                // Log des premières clés de la première ligne pour debugger
                $first = reset($rows);
                if (is_array($first)) {
                    @error_log('[prestashop_bulk_action] data hook: first row keys(before)=' . implode(',', array_keys($first)));
                }
            }

            // Déterminer l'id de boutique courant
            $shopId = 0;
            try {
                if (isset(\Context::getContext()->shop->id)) {
                    $shopId = (int) \Context::getContext()->shop->id;
                }
            } catch (\Throwable $e) {
                $shopId = 0;
            }

            // Récupérer available_for_order (product_shop) et out_of_stock (stock_available)
            $in = implode(',', array_map('intval', array_unique($ids)));
            $prefix = defined('_DB_PREFIX_') ? _DB_PREFIX_ : 'ps_';
            $sql = 'SELECT ps.id_product, ps.available_for_order
                    FROM ' . pSQL($prefix) . 'product_shop ps
                    WHERE ps.id_product IN (' . $in . ')'
                . ($shopId > 0 ? ' AND ps.id_shop=' . (int) $shopId : '');

            $rowsMap = [];
            $outOfStockMap = [];
            if (!empty($ids)) {
                try {
                    $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) ?: [];
                    foreach ($result as $r) {
                        $rowsMap[(int) $r['id_product']] = (int) $r['available_for_order'];
                    }
                } catch (\Throwable $e) {
                    // En cas d'erreur DB, fallback: considérer non achetable
                }
            }

            // Charger out_of_stock depuis stock_available (id_product_attribute = 0)
            if (!empty($ids)) {
                $shopGroupId = 0;
                try {
                    if (isset(\Context::getContext()->shop->id_shop_group)) {
                        $shopGroupId = (int) \Context::getContext()->shop->id_shop_group;
                    }
                } catch (\Throwable $e) {
                    $shopGroupId = 0;
                }

                $stockSql = 'SELECT sa.id_product, sa.out_of_stock, sa.id_shop'
                    . ' FROM ' . pSQL($prefix) . 'stock_available sa'
                    . ' WHERE sa.id_product IN (' . $in . ')'
                    . ' AND sa.id_product_attribute = 0'
                    . ($shopId > 0
                        ? ' AND (sa.id_shop = ' . (int) $shopId
                            . ' OR (sa.id_shop = 0 AND sa.id_shop_group = ' . (int) $shopGroupId . '))'
                        : '');

                try {
                    $stockRows = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($stockSql) ?: [];
                    foreach ($stockRows as $sr) {
                        $pid = (int) $sr['id_product'];
                        $out = (int) $sr['out_of_stock'];
                        $rowShopId = isset($sr['id_shop']) ? (int) $sr['id_shop'] : 0;
                        // Priorise la valeur spécifique boutique si disponible, sinon garde la valeur de groupe
                        if ($rowShopId === $shopId || !isset($outOfStockMap[$pid])) {
                            $outOfStockMap[$pid] = $out;
                        }
                    }
                } catch (\Throwable $e) {
                    // silencieux: en cas d'échec, on conservera le fallback « Défaut » (2)
                }
            }

            // Injecter/mettre à jour les champs dans chaque ligne
            foreach ($rows as $i => $row) {
                $pid = isset($row['id_product']) ? (int) $row['id_product'] : 0;
                $isBuyable = isset($rowsMap[$pid]) ? (bool) $rowsMap[$pid] : (bool) ($row['available_for_order'] ?? false);
                $row['available_for_order'] = (int) $isBuyable;
                $row['achetable_label'] = $isBuyable ? $this->l('Oui') : $this->l('Non');

                // Sur commande: 0 = Non, 1 = Oui, 2 = Défaut
                $out = $outOfStockMap[$pid] ?? ($row['out_of_stock'] ?? 2);
                $out = (int) $out;
                if (!in_array($out, [0,1,2], true)) {
                    $out = 2;
                }
                $row['out_of_stock'] = $out;
                $row['sur_commande_label'] = ($out === 1)
                    ? $this->l('Oui')
                    : ($out === 0 ? $this->l('Non') : $this->l('Défaut'));
                $rows[$i] = $row;
            }
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                $first = reset($rows);
                if (is_array($first)) {
                    @error_log('[prestashop_bulk_action] data hook: first row has achetable_label=' . (array_key_exists('achetable_label', $first) ? 'yes' : 'no'));
                }
            }

            // Remplacer les records à l'intérieur de l'objet GridData existant (sans le remplacer),
            // pour être 100% compatible avec le passage par référence utilisé par le hook.
            try {
                $ref = new \ReflectionObject($data);
                if ($ref->hasProperty('records')) {
                    $prop = $ref->getProperty('records');
                    $prop->setAccessible(true);
                    $prop->setValue($data, new RecordCollection(array_values($rows)));
                }
            } catch (\Throwable $e) {
                // En cas d'échec (versions très anciennes), fallback: recréer GridData
                $params['data'] = new GridData(
                    new RecordCollection(array_values($rows)),
                    $data->getRecordsTotal(),
                    $data->getQuery()
                );
            }
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                @error_log('[prestashop_bulk_action] hookActionProductGridDataModifier: finished');
            }
        } catch (\Throwable $e) {
            // silencieux: ne pas casser la page produits
            if (defined('_PS_MODE_DEV_') && _PS_MODE_DEV_) {
                @error_log('[prestashop_bulk_action] data hook: error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Injecte un fichier JS dans le Back Office qui ajoute nos routes au routeur JS
     * via window.prestashop.customRoutes. Cela permet de contourner les cas où
     * fos_js_routes.json n'est pas encore à jour, tout en restant non intrusif.
     */
    public function hookActionAdminControllerSetMedia(array $params)
    {
        try {
            if (!isset($this->context) || !isset($this->context->controller)) {
                return;
            }
            // Chemin public du module: $this->_path pointe sur /modules/<module>/ depuis le BO
            $this->context->controller->addJS($this->_path . 'views/js/psba_routes.js');
        } catch (\Throwable $e) {
            // silencieux
        }
    }
}
