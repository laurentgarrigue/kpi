<?php

namespace App\PublicResults;

/**
 * Libellés d'attente des équipes d'un match non encore attribué, déduits de son code
 * (ex. « [V11-P12] » → « (Winner game #11) », « (Loser game #12) »).
 */
final class MatchLabelParser
{
    /** @return array<int, string> libellés par position (0 = équipe A, 1 = équipe B…) */
    public function parse(string $libelle): array
    {
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
                $result[$j] = match ($codeLettres[1]) {
                    'T', 'D' => '(Team ' . $codeNumero[1] . ')',
                    'V', 'G', 'W' => '(Winner game #' . $codeNumero[1] . ')',
                    'P', 'L' => '(Loser game #' . $codeNumero[1] . ')',
                    default => $code,
                };
            } else {
                $n = (int) $codeNumero[1];
                $ord = match ($n) {
                    1 => '1st',
                    2 => '2nd',
                    3 => '3rd',
                    default => $n . 'th',
                };
                $result[$j] = "($ord Group {$codeLettres[1]})";
            }
        }

        return $result;
    }
}
