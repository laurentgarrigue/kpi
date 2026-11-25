<?php

namespace App\Designation\Facts;

/** Réponses d'un bilan de journée soumis, lues pour le relevé des faits. */
final class BilanAnswers
{
    /** @param array<string, mixed> $answers */
    public function __construct(
        public readonly int $id,
        public readonly array $answers,
    ) {
    }
}
