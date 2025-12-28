<?php

declare(strict_types=1);

namespace Api\Controller\Traits;

use Api\Response\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait for generating file download responses.
 */
trait FileResponseTrait
{
    /**
     * Create a file download response.
     *
     * @param string $content File content
     * @param string $contentType MIME type (e.g., text/csv, application/pdf)
     * @param string $filename Filename for Content-Disposition
     * @return ResponseInterface
     */
    protected function createFileResponse(string $content, string $contentType, string $filename): ResponseInterface
    {
        return new Response(
            $content,
            200,
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }
}
