<?php

namespace PrestaShop\Module\PrestashopBulkAction\Utils;

use Symfony\Component\HttpFoundation\Request;

/**
 * Utilitaire pur pour l'extraction robuste des IDs sélectionnés depuis une Request.
 * Séparé pour permettre des tests unitaires sans dépendre du framework PrestaShop.
 */
class SelectionExtractor
{
    /**
     * Variante testable sans dépendance Request: prend directement le tableau POST.
     *
     * @param array<string,mixed> $post
     * @return array<int, int>
     */
    public static function fromParameters(array $post)
    {
        $normalizeToArray = function ($value) {
            if (is_array($value)) {
                return $value;
            }
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    return [];
                }
                // Try JSON list first
                $firstChar = isset($value[0]) ? $value[0] : '';
                if ($firstChar === '[' && substr($value, -1) === ']') {
                    $decoded = json_decode($value, true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }
                }
                // Fallback: CSV or single ID as string
                return preg_split('/\s*,\s*/', $value);
            }
            if (is_int($value) || is_float($value)) {
                return [(int) $value];
            }
            return [];
        };

        $candidates = [];

        $candidates[] = $normalizeToArray(isset($post['selected']) ? $post['selected'] : null);
        $candidates[] = $normalizeToArray(isset($post['selection']) ? $post['selection'] : null);
        $candidates[] = $normalizeToArray(isset($post['ids']) ? $post['ids'] : null);
        // Compatible avec l'extension AjaxBulkAction de PrestaShop (requestParamName par défaut: bulk_ids)
        $candidates[] = $normalizeToArray(isset($post['bulk_ids']) ? $post['bulk_ids'] : null);

        if (isset($post['product_bulk']) && is_array($post['product_bulk'])) {
            $bulk = $post['product_bulk'];
            if (isset($bulk['ids'])) {
                $candidates[] = $normalizeToArray($bulk['ids']);
            }
            if (isset($bulk['selected'])) {
                $candidates[] = $normalizeToArray($bulk['selected']);
            }
            // Nouveau: certains formulaires envoient directement product_bulk[] = 19&product_bulk[] = 18
            // sans clé interne. Dans ce cas, considérer le tableau comme la sélection.
            $isAssoc = false;
            foreach ($bulk as $k => $_v) { if (!is_int($k)) { $isAssoc = true; break; } }
            if (!$isAssoc && !empty($bulk)) {
                $candidates[] = $bulk;
            }
        }

        foreach ($candidates as $cand) {
            if (is_array($cand) && !empty($cand)) {
                return $cand;
            }
        }

        return [];
    }

    /**
     * Extrait une liste d'identifiants de produits depuis la requête HTTP.
     *
     * Accepte plusieurs formats: tableau, CSV, JSON array, entier/chaîne scalaire,
     * et structures imbriquées sous product_bulk[ids|selected].
     *
     * @return array<int, int> Liste d'IDs telle que reçue (non filtrée >0, non unique)
     */
    public static function fromRequest(Request $request)
    {
        // S'appuie sur fromParameters pour éviter les dépendances pendant les tests unitaires.
        return self::fromParameters($request->request->all());
    }
}
