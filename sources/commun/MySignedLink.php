<?php

/**
 * Accès aux PDF admin legacy (admin/Feuille*.php) — DOC/specs/PDF_GOTENBERG.md § 1.2 et phase 0.
 *
 * Ces pages répondaient à n'importe qui (aucun contrôle depuis leur ouverture à app4, qui n'a pas de session PHP).
 * Elles exigent désormais :
 *  - soit une session de l'admin legacy (profil connecté) ;
 *  - soit un lien signé par api2 (POST /admin/pdf/legacy-link), qui a vérifié le rôle et le périmètre de
 *    l'utilisateur : HMAC-SHA256 du chemin et de tous les paramètres, valable 5 minutes.
 *
 * ⚠️ Même schéma que sources/api2/src/Pdf/PdfLinkSigner.php (même secret PDF_LINK_SECRET) : les garder en phase.
 */
class MySignedLink
{
    const EXPIRES = 'expires';
    const SIGNATURE = 'signature';

    /** Le lien est-il signé par api2 pour ce chemin et ces paramètres, et encore valable ? */
    public static function isValid($path, array $parameters, $secret, $now)
    {
        $signature = isset($parameters[self::SIGNATURE]) ? $parameters[self::SIGNATURE] : null;
        $expires = isset($parameters[self::EXPIRES]) ? $parameters[self::EXPIRES] : null;
        unset($parameters[self::SIGNATURE]);

        if (!is_string($signature) || !is_string($expires) || !ctype_digit($expires) || (int) $expires < $now
            || !is_string($secret) || $secret === '') {
            return false;
        }

        return hash_equals(self::signature($path, $parameters, $secret), $signature);
    }

    /** Arrête la page (403) sans session de l'admin legacy ni lien signé valable. */
    public static function requirePdfAccess()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['Profile']) && (int) $_SESSION['Profile'] > 0) {
            return;
        }

        $path = '/admin/' . basename($_SERVER['SCRIPT_NAME']);
        if (self::isValid($path, $_GET, getenv('PDF_LINK_SECRET'), time())) {
            return;
        }

        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo "Accès refusé : lien expiré ou non autorisé. Rouvrez le document depuis l'administration.";
        exit;
    }

    private static function signature($path, array $parameters, $secret)
    {
        ksort($parameters);
        $payload = $path . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $secret, true)), '+/', '-_'), '=');
    }
}
