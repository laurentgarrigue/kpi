<?php

namespace App\Cms;

/**
 * Erreurs de validation d'un formulaire d'administration : un code par champ (`required`, `invalid`, `taken`,
 * `reserved`, `too_long`…), traduit par app4. Levée en bloc pour que l'écran montre toutes les erreurs.
 */
final class ValidationErrors extends \RuntimeException
{
    /** @var array<string, string> */
    private array $errors = [];

    public function add(string $field, string $code): self
    {
        $this->errors[$field] ??= $code;

        return $this;
    }

    public function has(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->errors;
    }

    public function throwIfAny(): void
    {
        if ($this->errors !== []) {
            throw $this;
        }
    }

    /** Vérifie une longueur maximale ; renvoie la valeur nettoyée. */
    public function maxLength(string $field, string $value, int $max): string
    {
        $value = trim($value);
        if (mb_strlen($value) > $max) {
            $this->add($field, 'too_long');
        }

        return $value;
    }
}
