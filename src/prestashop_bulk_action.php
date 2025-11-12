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
