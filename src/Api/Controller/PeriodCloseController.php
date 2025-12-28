<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Controller\Traits\SafeExceptionHandlerTrait;

use Api\Response\JsonResponse;
use Domain\Approval\Entity\Approval;
use Domain\Approval\Repository\ApprovalRepositoryInterface;
use Domain\Approval\ValueObject\ApprovalReason;
use Domain\Approval\ValueObject\ApprovalType;
use Domain\Approval\ValueObject\CreateApprovalRequest;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Period Close API Controller.
 * 
 * Handles period closing requests which require approval.
 */
final class PeriodCloseController
{
    use SafeExceptionHandlerTrait;

    public function __construct(
        private readonly ApprovalRepositoryInterface $approvalRepository,
        private readonly \Domain\Reporting\Repository\ClosedPeriodRepositoryInterface $closedPeriodRepository
    ) {
    }

    /**
     * POST /api/v1/companies/{companyId}/period-close
     * 
     * Body:
     *   - start_date: ISO date
     *   - end_date: ISO date
     *   - net_income_cents: int
     */
    public function requestClose(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');
        $userId = $request->getAttribute('userId');

        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        if ($userId === null) {
            return JsonResponse::error('Authentication required', 401);
        }

        $body = $request->getParsedBody();
        $startDate = $body['start_date'] ?? null;
        $endDate = $body['end_date'] ?? null;
        $netIncomeCents = (int)($body['net_income_cents'] ?? 0);

        // Validate required fields
        if (!$startDate || !$endDate) {
            return JsonResponse::error('start_date and end_date are required', 422);
        }

        // Validate date formats
        if (!$this->isValidDate($startDate)) {
            return JsonResponse::error('Invalid start_date format. Use YYYY-MM-DD', 422);
        }
        if (!$this->isValidDate($endDate)) {
            return JsonResponse::error('Invalid end_date format. Use YYYY-MM-DD', 422);
        }

        try {
            // Create a unique entity ID for this period close request
            $entityId = sprintf('period-close-%s-%s-%s', $companyId, $startDate, $endDate);

            // Check for existing pending request for same period
            $existing = $this->approvalRepository->findByEntity('period_close', $entityId);
            if ($existing && $existing->status()->isPending()) {
                return JsonResponse::error('A period close request for this period is already pending', 409);
            }

            // Create the approval request
            $approvalRequest = new CreateApprovalRequest(
                companyId: CompanyId::fromString($companyId),
                approvalType: ApprovalType::PERIOD_CLOSE,
                entityType: 'period_close',
                entityId: $entityId,
                reason: ApprovalReason::periodClose($startDate, $endDate, $netIncomeCents),
                requestedBy: UserId::fromString($userId),
                amountCents: abs($netIncomeCents),
                priority: ApprovalType::PERIOD_CLOSE->getDefaultPriority()
            );

            $approval = Approval::request($approvalRequest);
            $this->approvalRepository->save($approval);

            return JsonResponse::created([
                'approval_id' => $approval->id()->toString(),
                'status' => $approval->status()->value,
                'message' => 'Period close request submitted for approval',
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'net_income_cents' => $netIncomeCents,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), $this->getExceptionStatusCode($e));
        }
    }

    /**
     * GET /api/v1/companies/{companyId}/period-close
     * 
     * List closed periods for the company.
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        $companyId = $request->getAttribute('companyId');

        if ($companyId === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $periods = $this->closedPeriodRepository->findByCompany(CompanyId::fromString($companyId));

            return JsonResponse::success([
                'data' => array_map(fn($p) => [
                    'id' => $p->id(),
                    'start_date' => $p->startDate()->format('Y-m-d'),
                    'end_date' => $p->endDate()->format('Y-m-d'),
                    'closed_at' => $p->closedAt()->format('Y-m-d H:i:s'),
                    'net_income_cents' => $p->netIncomeCents(),
                    'closed_by' => $p->closedBy()->toString(),
                    'approval_id' => $p->approvalId(),
                    'chain_hash' => $p->chainHash()?->toString(),
                ], $periods)
            ]);
        } catch (\Throwable $e) {
            return JsonResponse::error($this->getSafeErrorMessage($e), 500);
        }
    }

    /**
     * Validate date format YYYY-MM-DD.
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
