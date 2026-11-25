<?php

namespace App\ClubRanking;

/**
 * Club organisateur d'une journée, tel que l'identifie le classement national des clubs et le suivi des Désignations :
 * le code saisi (`Code_organisateur`) fait foi ; à défaut, le libellé saisi identifie le club (entités HTML et accents
 * ignorés), sauf si deux clubs portent le même libellé.
 */
final class OrganiserClubResolver
{
    /** @var array<string, string> libellé normalisé → code du club */
    private readonly array $codesByLabel;

    /** @param array<string, string> $labelsByCode libellé de chaque club, indexé par son code */
    public function __construct(private readonly array $labelsByCode)
    {
        $codes = [];
        foreach ($labelsByCode as $code => $label) {
            $key = NameNormalizer::normalizeLabel($label);
            $codes[$key] = isset($codes[$key]) ? '' : (string) $code;
        }
        unset($codes['']);

        $this->codesByLabel = array_filter($codes, static fn (string $code): bool => $code !== '');
    }

    /** @return string code du club, vide si le club ne peut pas être identifié */
    public function code(?string $code, ?string $label): string
    {
        $code = trim((string) $code);

        return $code !== '' ? $code : ($this->codesByLabel[NameNormalizer::normalizeLabel((string) $label)] ?? '');
    }

    /** Libellé du club identifié, sinon le libellé saisi tel quel. */
    public function label(?string $code, ?string $label): string
    {
        $resolved = $this->code($code, $label);

        return $this->labelsByCode[$resolved] ?? trim((string) $label);
    }
}
