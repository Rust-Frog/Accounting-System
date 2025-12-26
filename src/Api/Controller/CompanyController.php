<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Response\JsonResponse;
use Domain\Company\Entity\Company;
use Domain\Company\Repository\CompanyRepositoryInterface;
use Domain\Company\ValueObject\Address;
use Domain\Company\ValueObject\CompanyId;
use Domain\Company\ValueObject\TaxIdentifier;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\ValueObject\Currency;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Company controller for company management.
 */
final class CompanyController
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly ?\Domain\Audit\Service\SystemActivityService $activityService = null
    ) {
    }

    /**
     * GET /api/v1/companies
     * List all companies (for now, returns all active companies).
     */
    public function list(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $companies = $this->companyRepository->findAll();
            
            return JsonResponse::success(
                array_map(fn($c) => $this->formatCompany($c), $companies)
            );
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/companies/{id}
     */
    public function get(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($id === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $company = $this->companyRepository->findById(
                CompanyId::fromString($id)
            );

            if ($company === null) {
                return JsonResponse::error('Company not found', 404);
            }

            return JsonResponse::success($this->formatCompany($company));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/companies
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        // Validate required fields
        $required = ['name', 'legal_name', 'tax_id'];
        foreach ($required as $field) {
            if (empty($body[$field])) {
                return JsonResponse::error("Missing required field: $field", 422);
            }
        }

        try {
            // Address is required by domain
            $addressData = $body['address'] ?? [];
            $address = Address::create(
                $addressData['street'] ?? 'Not Provided',
                $addressData['city'] ?? 'Not Provided',
                $addressData['state'] ?? null,
                $addressData['postal_code'] ?? null,
                $addressData['country'] ?? 'US'
            );

            $currency = Currency::from($body['currency'] ?? 'USD');

            $company = Company::create(
                $body['name'],
                $body['legal_name'],
                TaxIdentifier::fromString($body['tax_id']),
                $address,
                $currency
            );

            $this->companyRepository->save($company);

            // Log company creation
            $this->activityService?->log(
                activityType: 'company.created',
                entityType: 'company',
                entityId: $company->id()->toString(),
                description: "Company {$company->companyName()} created",
                actorUserId: \Domain\Identity\ValueObject\UserId::fromString($request->getAttribute('user_id') ?? ''),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'info',
                metadata: ['currency' => $currency->value]
            );

            return JsonResponse::created($this->formatCompany($company));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/companies/{id}/activate
     */
    public function activate(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($id === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $company = $this->companyRepository->findById(
                CompanyId::fromString($id)
            );

            if ($company === null) {
                return JsonResponse::error('Company not found', 404);
            }

            // Get the user ID from the request (set by auth middleware)
            $userId = $request->getAttribute('user_id');
            $company->activate(UserId::fromString($userId));
            $this->companyRepository->save($company);

            // Log company activation
            $this->activityService?->log(
                activityType: 'company.activated',
                entityType: 'company',
                entityId: $id,
                description: "Company {$company->companyName()} activated",
                actorUserId: UserId::fromString($userId),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'info'
            );

            return JsonResponse::success($this->formatCompany($company));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/companies/{id}/suspend
     */
    public function suspend(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($id === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $company = $this->companyRepository->findById(
                CompanyId::fromString($id)
            );

            if ($company === null) {
                return JsonResponse::error('Company not found', 404);
            }

            $body = $request->getParsedBody();
            $reason = $body['reason'] ?? 'No reason provided';

            $userId = $request->getAttribute('user_id');
            $company->suspend(UserId::fromString($userId), $reason);
            $this->companyRepository->save($company);

            // Log company suspension
            $this->activityService?->log(
                activityType: 'company.suspended',
                entityType: 'company',
                entityId: $id,
                description: "Company {$company->companyName()} suspended: {$reason}",
                actorUserId: UserId::fromString($userId),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'warning',
                metadata: ['reason' => $reason]
            );

            return JsonResponse::success($this->formatCompany($company));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/companies/{id}/reactivate
     */
    public function reactivate(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($id === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $company = $this->companyRepository->findById(
                CompanyId::fromString($id)
            );

            if ($company === null) {
                return JsonResponse::error('Company not found', 404);
            }

            $userId = $request->getAttribute('user_id');
            $company->reactivate(UserId::fromString($userId));
            $this->companyRepository->save($company);

            return JsonResponse::success($this->formatCompany($company));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/v1/companies/{id}/deactivate
     */
    public function deactivate(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');
        if ($id === null) {
            return JsonResponse::error('Company ID required', 400);
        }

        try {
            $company = $this->companyRepository->findById(
                CompanyId::fromString($id)
            );

            if ($company === null) {
                return JsonResponse::error('Company not found', 404);
            }

            $body = $request->getParsedBody();
            $reason = $body['reason'] ?? 'No reason provided';

            $userId = $request->getAttribute('user_id');
            $company->deactivate(UserId::fromString($userId), $reason);
            $this->companyRepository->save($company);

            // Log company void/deactivation
            $this->activityService?->log(
                activityType: 'company.voided',
                entityType: 'company',
                entityId: $id,
                description: "Company {$company->companyName()} voided: {$reason}",
                actorUserId: UserId::fromString($userId),
                actorUsername: $request->getAttribute('username'),
                actorIpAddress: $request->getServerParams()['REMOTE_ADDR'] ?? null,
                severity: 'critical',
                metadata: ['reason' => $reason]
            );

            return JsonResponse::success($this->formatCompany($company));
        } catch (\Throwable $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * Format company for API response.
     */
    private function formatCompany(Company $company): array
    {
        $address = $company->address();

        return [
            'id' => $company->id()->toString(),
            'name' => $company->companyName(),
            'legal_name' => $company->legalName(),
            'tax_id' => $company->taxId()->toString(),
            'currency' => $company->currency()->value,
            'status' => $company->status()->value,
            'address' => [
                'street' => $address->street(),
                'city' => $address->city(),
                'state' => $address->state(),
                'postal_code' => $address->postalCode(),
                'country' => $address->country(),
            ],
            'created_at' => $company->createdAt()->format('Y-m-d\TH:i:s\Z'),
            'updated_at' => $company->updatedAt()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
