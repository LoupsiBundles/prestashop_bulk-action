<?php

namespace PrestaShop\Module\PrestashopBulkAction\Controller\Admin;

use Context;
use Db;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use PrestaShop\Module\PrestashopBulkAction\Utils\SelectionExtractor;

class BulkActionController extends PrestaShopAdminController
{
    /**
     * Rend les produits sélectionnés disponibles à la vente (available_for_order = 1) pour la boutique courante.
     */
    public function makeAvailableAction(Request $request)
    {
        $ids = SelectionExtractor::fromRequest($request);

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'errors' => [
                    $this->trans('Aucun produit sélectionné.', [], 'Modules.Prestashopbulkaction.Admin'),
                ],
            ]);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, function ($v) { return $v > 0; });

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'errors' => [
                    $this->trans('Aucun produit valide.', [], 'Modules.Prestashopbulkaction.Admin'),
                ],
            ]);
        }

        $shopId = (int) Context::getContext()->shop->id;

        $in = implode(',', $ids);
        $sql = 'UPDATE `'._DB_PREFIX_.'product_shop` SET `available_for_order` = 1 WHERE `id_shop` = '.(int) $shopId.' AND `id_product` IN ('.$in.')';

        try {
            $ok = Db::getInstance()->execute($sql);
            if ($ok) {
                return new JsonResponse([
                    'success' => true,
                ]);
            }
            return new JsonResponse([
                'success' => false,
                'errors' => [
                    $this->trans('Impossible de mettre à jour les produits sélectionnés.', [], 'Modules.Prestashopbulkaction.Admin'),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'errors' => [
                    $this->trans('Erreur lors de la mise à jour: %error%', ['%error%' => $e->getMessage()], 'Modules.Prestashopbulkaction.Admin'),
                ],
            ]);
        }
    }

    /**
     * Rend les produits sélectionnés indisponibles à la vente (available_for_order = 0) pour la boutique courante.
     */
    public function makeUnavailableAction(Request $request)
    {
        $ids = SelectionExtractor::fromRequest($request);

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'errors' => [
                    $this->trans('Aucun produit sélectionné.', [], 'Modules.Prestashopbulkaction.Admin'),
                ],
            ]);
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, function ($v) { return $v > 0; });

        if (empty($ids)) {
            return new JsonResponse([
                'success' => false,
                'errors' => [
                    $this->trans('Aucun produit valide.', [], 'Modules.Prestashopbulkaction.Admin'),
                ],
            ]);
        }

        $shopId = (int) Context::getContext()->shop->id;

        $in = implode(',', $ids);
        $sql = 'UPDATE `'._DB_PREFIX_.'product_shop` SET `available_for_order` = 0 WHERE `id_shop` = '.(int) $shopId.' AND `id_product` IN ('.$in.')';

        try {
            $ok = Db::getInstance()->execute($sql);
            if ($ok) {
                return new JsonResponse([
                    'success' => true,
                ]);
            }
            return new JsonResponse([
                'success' => false,
                'errors' => [
                    $this->trans('Impossible de mettre à jour les produits sélectionnés.', [], 'Modules.Prestashopbulkaction.Admin'),
                ],
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'errors' => [
                    $this->trans('Erreur lors de la mise à jour: %error%', ['%error%' => $e->getMessage()], 'Modules.Prestashopbulkaction.Admin'),
                ],
            ]);
        }
    }
}
