<?php

declare(strict_types=1);

namespace WicketORM\BulkImport;

/**
 * Value object returned by FileParserService::parseFile().
 *
 * @see FileParserService::parseFile()
 */
class ParseResult
{
    /**
     * @param array<int, array<string, string>> $rows            Normalized rows keyed by internal field name.
     * @param array<int, string>                $missingHeaders  Required headers not found in the CSV.
     * @param string|null                       $error           Non-null when parsing failed entirely.
     * @param int                               $totalCount      Total data rows parsed (excludes header).
     */
    public function __construct(
        public readonly array $rows = [],
        public readonly array $missingHeaders = [],
        public readonly ?string $error = null,
        public readonly int $totalCount = 0,
    ) {}

    public function hasError(): bool
    {
        return $this->error !== null;
    }

    public function hasMissingHeaders(): bool
    {
        return $this->missingHeaders !== [];
    }
}
