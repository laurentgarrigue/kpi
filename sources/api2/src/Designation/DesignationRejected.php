<?php

namespace App\Designation;

/** Écriture de Désignation refusée : `error` est renvoyé à app4, `status` est le code HTTP. */
final class DesignationRejected extends \RuntimeException
{
    public const FORBIDDEN = 'forbidden';
    public const NOT_FOUND = 'not_found';
    public const INVALID = 'invalid';
    public const LICENCE_UNKNOWN = 'licence_unknown';
    public const SCOPE_UNKNOWN = 'scope_unknown';
    public const DUPLICATE = 'duplicate';

    public function __construct(public readonly string $error, public readonly int $status)
    {
        parent::__construct($error);
    }

    public static function forbidden(): self
    {
        return new self(self::FORBIDDEN, 403);
    }

    public static function notFound(): self
    {
        return new self(self::NOT_FOUND, 404);
    }

    public static function unprocessable(string $code): self
    {
        return new self($code, 422);
    }
}
