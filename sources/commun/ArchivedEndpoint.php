<?php

/**
 * Garde des points d'entrée legacy archivés (connector/, live/) :
 * DOC/developer/reference/LEGACY_ARCHIVED_ENDPOINTS.md.
 *
 * À appeler en première instruction : `archivedEndpointGuard(__FILE__);`. Appelé directement en HTTP, le script
 * répond 410 Gone avant toute inclusion ou accès base. La CLI passe, ainsi qu'un script inclus par un autre
 * (feuille de marque legacy → live/create_cache_match.php, GestionOperations → connector/replace_evenement.php).
 * Ce fichier ne fait rien au chargement : l'appeler directement en HTTP est sans effet.
 */

const ARCHIVED_ENDPOINT_HTTP_STATUS = 410;
const ARCHIVED_ENDPOINT_DOC = 'DOC/developer/reference/LEGACY_ARCHIVED_ENDPOINTS.md';

function archivedEndpointGuard(string $file): void
{
    if (PHP_SAPI === 'cli' || isIncludedByAnotherScript($file)) {
        return;
    }

    http_response_code(ARCHIVED_ENDPOINT_HTTP_STATUS);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo "410 Gone : point d'entrée legacy archivé (" . ARCHIVED_ENDPOINT_DOC . ").\n";
    exit;
}

/** Fermé par défaut : sans SCRIPT_FILENAME résoluble, le script est traité comme appelé directement. */
function isIncludedByAnotherScript(string $file): bool
{
    $target = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');

    return $target !== false && $target !== realpath($file);
}
