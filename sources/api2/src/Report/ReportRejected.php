<?php

namespace App\Report;

/** Opération sur un rapport refusée : `error` est renvoyé à app4, `status` est le code HTTP. */
final class ReportRejected extends \RuntimeException
{
    public const FORBIDDEN = 'forbidden';
    public const NOT_FOUND = 'not_found';
    public const TYPE_UNKNOWN = 'type_unknown';
    public const SCOPE_NOT_ALLOWED = 'scope_not_allowed';
    public const AUTHOR_NOT_OFFICIAL = 'author_not_official';
    public const NOT_DRAFT = 'not_draft';
    public const NOT_SUBMITTED = 'not_submitted';
    public const REASON_REQUIRED = 'reason_required';
    public const INVALID_ANSWERS = 'invalid_answers';

    /** @param array<string, string> $fieldErrors */
    public function __construct(public readonly string $error, public readonly int $status, public readonly array $fieldErrors = [])
    {
        parent::__construct($error);
    }
}
