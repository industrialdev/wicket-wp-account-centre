<?php

declare(strict_types=1);

namespace WicketORM\BulkImport;

/**
 * CSV file parser for the bulk import pipeline.
 *
 * Parses CSV files into normalized row arrays using configurable column
 * definitions. Supports alias-aware, case-insensitive header matching so
 * users can use variations like "First Name", "first_name", or "firstname".
 *
 * Column definitions are injected via the constructor so different import
 * contexts (membership, OBA, etc.) can define their own field specs. The
 * `wicket_import_csv_columns` filter allows child themes to override them.
 */
class FileParserService
{
    /**
     * Default column definitions for membership import.
     *
     * Each entry:
     *   - header:   canonical column name.
     *   - field:    internal field name used by ValidationService and staging.
     *   - required: whether the column must be present and non-empty.
     *   - aliases:  alternative header names matched case-insensitively.
     *
     * @var array<int, array{header: string, field: string, required: bool, aliases: string[]}>
     */
    private array $columns;

    /**
     * @param array<int, array{header: string, field: string, required: bool, aliases: string[]}> $columns
     */
    public function __construct(array $columns)
    {
        $this->columns = $columns;
    }

    /**
     * Create an instance with default membership import columns,
     * optionally filtered by `wicket_import_csv_columns`.
     *
     * @param array $context Import context (e.g. ['source' => 'membership']).
     * @return static
     */
    public static function createWithDefaults(array $context = []): static
    {
        $columns = static::defaultColumns();
        $columns = apply_filters('wicket_import_csv_columns', $columns, $context);

        return new static($columns);
    }

    /**
     * Default column definitions for membership import.
     *
     * @return array<int, array{header: string, field: string, required: bool, aliases: string[]}>
     */
    public static function defaultColumns(): array
    {
        return [
            [
                'header'   => 'first_name',
                'field'    => 'first_name',
                'required' => true,
                'aliases'  => ['first_name', 'first name', 'firstname', 'first', 'given_name', 'given name'],
            ],
            [
                'header'   => 'last_name',
                'field'    => 'last_name',
                'required' => true,
                'aliases'  => ['last_name', 'last name', 'lastname', 'last', 'surname', 'family_name', 'family name'],
            ],
            [
                'header'   => 'email',
                'field'    => 'email',
                'required' => true,
                'aliases'  => ['email', 'e-mail', 'email_address', 'email address', 'e_mail'],
            ],
            [
                'header'   => 'phone',
                'field'    => 'phone',
                'required' => false,
                'aliases'  => ['phone', 'phone_number', 'phone number', 'mobile', 'cell', 'telephone'],
            ],
            [
                'header'   => 'address_line_1',
                'field'    => 'address_line_1',
                'required' => false,
                'aliases'  => ['address_line_1', 'address line 1', 'address', 'street', 'address1'],
            ],
            [
                'header'   => 'address_line_2',
                'field'    => 'address_line_2',
                'required' => false,
                'aliases'  => ['address_line_2', 'address line 2', 'address2', 'unit', 'suite'],
            ],
            [
                'header'   => 'city',
                'field'    => 'city',
                'required' => false,
                'aliases'  => ['city'],
            ],
            [
                'header'   => 'state',
                'field'    => 'state',
                'required' => false,
                'aliases'  => ['state', 'province', 'region'],
            ],
            [
                'header'   => 'zip',
                'field'    => 'zip',
                'required' => false,
                'aliases'  => ['zip', 'zip_code', 'zip code', 'postal_code', 'postal code', 'postcode', 'postal'],
            ],
            [
                'header'   => 'country',
                'field'    => 'country',
                'required' => false,
                'aliases'  => ['country', 'country_code', 'country code'],
            ],
        ];
    }

    /**
     * Parse a CSV file into normalized rows.
     *
     * Uses fgetcsv for RFC 4180 compliant reading. Performs alias-aware,
     * case-insensitive header matching. Skips blank rows.
     *
     * @param string $filePath Absolute path to the uploaded CSV file.
     * @return ParseResult
     */
    public function parseFile(string $filePath): ParseResult
    {
        $resolved = realpath($filePath);

        if ($resolved === false || !is_file($resolved)) {
            return new ParseResult(error: 'The uploaded file could not be found.');
        }

        $handle = fopen($resolved, 'r');

        if ($handle === false) {
            return new ParseResult(error: 'Unable to open the uploaded file.');
        }

        try {
            $rawHeaders = fgetcsv($handle, 0, ',', '"', '\\');

            if ($rawHeaders === false) {
                return new ParseResult(error: 'The CSV file is empty or could not be read.');
            }

            $headerMap = [];
            $missing = [];

            foreach ($this->columns as $colDef) {
                $index = $this->resolveHeaderIndex($rawHeaders, $colDef);

                if ($index === false) {
                    if ($colDef['required']) {
                        $missing[] = $colDef['header'];
                    }
                } else {
                    $headerMap[$colDef['field']] = $index;
                }
            }

            if ($missing !== []) {
                return new ParseResult(
                    error: sprintf('Missing required column(s): %s.', implode(', ', $missing)),
                    missingHeaders: $missing,
                );
            }

            $rows = [];

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if (self::isBlankRow($row)) {
                    continue;
                }

                $normalized = [];

                foreach ($headerMap as $field => $colIndex) {
                    $normalized[$field] = trim((string) ($row[$colIndex] ?? ''));
                }

                $rows[] = $normalized;
            }

            return new ParseResult(rows: $rows, totalCount: count($rows));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Resolve the 0-based index of a column in a CSV header row.
     *
     * Matching is case-insensitive and alias-aware: any value in the column's
     * 'aliases' array that matches (after lowercasing and trimming) a header
     * cell is a hit. Falls back to matching the canonical 'header' value when
     * 'aliases' is absent.
     *
     * @param string[]                               $csvHeaders Headers from the first CSV row.
     * @param array{header: string, aliases?: string[]} $colDef  Column definition.
     * @return int|false 0-based index, or false if not found.
     */
    private function resolveHeaderIndex(array $csvHeaders, array $colDef): int|false
    {
        $aliases = array_map('strtolower', $colDef['aliases'] ?? [$colDef['header']]);

        foreach ($csvHeaders as $index => $header) {
            if (in_array(strtolower(trim((string) $header)), $aliases, true)) {
                return $index;
            }
        }

        return false;
    }

    /**
     * Check whether a parsed CSV row is blank (all fields empty or whitespace).
     *
     * @param string[] $row Fields from fgetcsv.
     */
    private static function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Return canonical CSV header names in template order.
     *
     * @return string[]
     */
    public function getTemplateHeaders(): array
    {
        return array_column($this->columns, 'header');
    }

    /**
     * Return only the headers marked as required.
     *
     * @return string[]
     */
    public function getRequiredHeaders(): array
    {
        return array_column(
            array_filter(
                $this->columns,
                static fn (array $col): bool => $col['required'],
            ),
            'header',
        );
    }
}
