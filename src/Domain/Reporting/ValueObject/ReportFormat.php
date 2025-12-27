<?php

declare(strict_types=1);

namespace Domain\Reporting\ValueObject;

enum ReportFormat: string
{
    case JSON = 'json';
    case PDF = 'pdf';
    case EXCEL = 'excel';
    case CSV = 'csv';
    case HTML = 'html';

    public function contentType(): string
    {
        return match ($this) {
            self::JSON => 'application/json',
            self::PDF => 'application/pdf',
            self::EXCEL => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::CSV => 'text/csv',
            self::HTML => 'text/html',
        };
    }

    public function fileExtension(): string
    {
        return match ($this) {
            self::JSON => 'json',
            self::PDF => 'pdf',
            self::EXCEL => 'xlsx',
            self::CSV => 'csv',
            self::HTML => 'html',
        };
    }
}
