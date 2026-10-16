<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/** Réponse JSON sans échappement des caractères accentués (format historique des endpoints publics). */
final class UnicodeJsonResponse extends JsonResponse
{
    /** @param array<string, string|list<string>> $headers */
    public function __construct(mixed $data = null, int $status = 200, array $headers = [])
    {
        parent::__construct(null, $status, $headers);
        $this->setEncodingOptions($this->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        $this->setData($data ?? new \ArrayObject());
    }
}
