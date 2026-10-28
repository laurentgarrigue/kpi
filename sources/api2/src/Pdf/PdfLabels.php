<?php

namespace App\Pdf;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/** Fixed texts of the documents in the language of the request (config/pdf/labels.yaml, PDF-DATA-04). */
class PdfLabels
{
    /** @var array<string, array<string, string>>|null */
    private ?array $labels = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/pdf/labels.yaml')]
        private readonly string $file,
    ) {
    }

    /** @return array<string, string> every text in the language, by key */
    public function forLanguage(string $lang): array
    {
        $this->labels ??= Yaml::parseFile($this->file);

        return array_map(static fn (array $texts): string => $texts[$lang] ?? $texts['fr'], $this->labels);
    }
}
