<?php

namespace App\PublicResults;

/**
 * Libellés d'attente des équipes d'un match non encore attribué, déduits de son code
 * (ex. « [V11-P12] » → « (Winner game #11) », « (Loser game #12) »), en anglais ou en français.
 */
final class MatchLabelParser
{
    /** @return array<int, string> libellés par position (0 = équipe A, 1 = équipe B…) */
    public function parse(string $libelle, string $lang = 'en'): array
    {
        $french = $lang === 'fr';
        $result = [];
        $parts = preg_split('/\[/', $libelle);
        if (!isset($parts[1]) || $parts[1] === '') {
            return $result;
        }
        $inner = preg_split('/\]/', $parts[1]);
        if ($inner[0] === '') {
            return $result;
        }
        $codes = preg_split('/[-\/*,;]/', $inner[0]);
        for ($j = 0; $j < 4; $j++) {
            if (!isset($codes[$j])) {
                continue;
            }
            $code = trim($codes[$j]);
            preg_match('/([A-Z_]+)/', $code, $codeLettres);
            preg_match('/([0-9]+)/', $code, $codeNumero);
            if (!isset($codeLettres[1], $codeNumero[1])) {
                continue;
            }
            $posL = strpos($code, $codeLettres[1]);
            $posN = strpos($code, $codeNumero[1]);
            if ($posN > $posL) {
                $number = $codeNumero[1];
                $result[$j] = match ($codeLettres[1]) {
                    'T', 'D' => $french ? "(Equipe $number)" : "(Team $number)",
                    'V', 'G', 'W' => $french ? "(Vainqueur match $number)" : "(Winner game #$number)",
                    'P', 'L' => $french ? "(Perdant match $number)" : "(Loser game #$number)",
                    default => $code,
                };
            } else {
                $n = (int) $codeNumero[1];
                $result[$j] = $french
                    ? sprintf('(%s Poule %s)', match ($n) { 1 => '1er', 2 => '2nd', default => $n . 'e' }, $codeLettres[1])
                    : sprintf('(%s Group %s)', match ($n) { 1 => '1st', 2 => '2nd', 3 => '3rd', default => $n . 'th' }, $codeLettres[1]);
            }
        }

        return $result;
    }

    /** Libellé du match sans son code d'attribution : « M12 [V1-V2] » → « M12 ». */
    public static function gameLabel(string $libelle): string
    {
        return trim((string) preg_replace('/\[[^\]]*\]/', '', $libelle));
    }
}
