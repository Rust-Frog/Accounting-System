<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

use Api\Response\JsonResponse;
use Domain\Company\ValueObject\CompanyId;
use Domain\Ledger\Entity\JournalEntry;
use Domain\Ledger\Repository\JournalEntryRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Journal controller for viewing immutable ledger entries.
 */
final class JournalController
{
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly JournalEntryRepositoryInterface $journalEntryRepository
    ) {
    }

    /**
     * GET /api/v1/companies/{companyId}/journal
     * List journal entries with pagination.
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $companyId = $this->getCompanyId($request);
            $queryParams = $request->getQueryParams();
            
            $page = max(1, (int) ($queryParams['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($queryParams['per_page'] ?? 20)));
            
            $entries = $this->journalEntryRepository->findByCompanyPaginated($companyId, $page, $perPage);
            $total = $this->journalEntryRepository->countByCompany($companyId);
            
            $data = array_map(fn(JournalEntry $entry) => $this->formatEntry($entry), $entries);

            return JsonResponse::success([
                'data' => $data,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * GET /api/v1/companies/{companyId}/journal/{id}
     * Get a single journal entry with full details.
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $id = $request->getAttribute('id');
            if ($id === null) {
                return JsonResponse::error('Journal entry ID required', 400);
            }

            $entry = $this->journalEntryRepository->findById($id);
            if ($entry === null) {
                return JsonResponse::error('Journal entry not found', 404);
            }

            return JsonResponse::success($this->formatEntryDetail($entry));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * Format entry for list display.
     */
    private function formatEntry(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'transaction_id' => $entry->transactionId->toString(),
            'entry_type' => $entry->entryType,
            'occurred_at' => $entry->occurredAt->format('Y-m-d H:i:s'),
            'content_hash' => substr($entry->contentHash->toString(), 0, 16) . '...',
            'has_chain_link' => $entry->previousHash !== null,
        ];
    }

    /**
     * Format entry with full details.
     */
    private function formatEntryDetail(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'company_id' => $entry->companyId->toString(),
            'transaction_id' => $entry->transactionId->toString(),
            'entry_type' => $entry->entryType,
            'bookings' => $entry->bookings,
            'occurred_at' => $entry->occurredAt->format('Y-m-d H:i:s'),
            'content_hash' => $entry->contentHash->toString(),
            'previous_hash' => $entry->previousHash?->toString(),
            'chain_hash' => $entry->getChainHash()?->toString(),
            'chain_verified' => $entry->previousHash !== null,
        ];
    }

    private function getCompanyId(ServerRequestInterface $request): CompanyId
    {
        $companyId = $request->getAttribute('companyId');
        if ($companyId === null) {
            throw new \InvalidArgumentException('Company ID required');
        }
        return CompanyId::fromString($companyId);
    }
}
