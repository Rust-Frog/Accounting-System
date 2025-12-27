<?php

declare(strict_types=1);

namespace Infrastructure\Persistence\Mysql\Hydrator;

use DateTimeImmutable;
use Domain\Approval\Entity\Approval;
use Domain\Approval\ValueObject\ApprovalId;
use Domain\Approval\ValueObject\ApprovalReason;
use Domain\Approval\ValueObject\ApprovalStatus;
use Domain\Approval\ValueObject\ApprovalType;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use ReflectionClass;

/**
 * Hydrates Approval entities from database rows and extracts data for persistence.
 */
final class ApprovalHydrator
{
    /**
     * Hydrate an Approval entity from a database row.
     *
     * @param array<string, mixed> $row
     */
    public function hydrate(array $row): Approval
    {
        $reflection = new ReflectionClass(Approval::class);
        $approval = $reflection->newInstanceWithoutConstructor();

        $this->setProperty($reflection, $approval, 'id', ApprovalId::fromString($row['id']));
        $this->setProperty($reflection, $approval, 'companyId', CompanyId::fromString($row['company_id']));
        $this->setProperty($reflection, $approval, 'approvalType', ApprovalType::from($row['approval_type']));
        $this->setProperty($reflection, $approval, 'entityType', $row['entity_type']);
        $this->setProperty($reflection, $approval, 'entityId', $row['entity_id']);

        $reasonReflector = new ReflectionClass(ApprovalReason::class);
        $reason = $reasonReflector->newInstanceWithoutConstructor();
        $this->setProperty($reasonReflector, $reason, 'description', $row['reason']);
        $this->setProperty($reasonReflector, $reason, 'type', ApprovalType::from($row['approval_type']));
        $this->setProperty($reasonReflector, $reason, 'details', []);

        $this->setProperty($reflection, $approval, 'reason', $reason);
        $this->setProperty($reflection, $approval, 'requestedBy', UserId::fromString($row['requested_by']));
        $this->setProperty($reflection, $approval, 'requestedAt', new DateTimeImmutable($row['requested_at']));
        $this->setProperty($reflection, $approval, 'amountCents', (int) $row['amount_cents']);
        $this->setProperty($reflection, $approval, 'priority', (int) $row['priority']);
        $this->setProperty(
            $reflection,
            $approval,
            'expiresAt',
            $row['expires_at'] !== null ? new DateTimeImmutable($row['expires_at']) : null
        );
        $this->setProperty($reflection, $approval, 'status', ApprovalStatus::from($row['status']));

        // Optional review fields
        $this->setProperty(
            $reflection,
            $approval,
            'reviewedBy',
            $row['reviewed_by'] !== null ? UserId::fromString($row['reviewed_by']) : null
        );
        $this->setProperty(
            $reflection,
            $approval,
            'reviewedAt',
            $row['reviewed_at'] !== null ? new DateTimeImmutable($row['reviewed_at']) : null
        );
        $this->setProperty($reflection, $approval, 'reviewNotes', $row['review_notes']);
        
        if (isset($row['proof_json']) && $row['proof_json']) {
            $proofData = json_decode($row['proof_json'], true);
            // We need a way to reconstruct ApprovalProof from array.
            // ApprovalProof::computeProofHash implementation suggests keys.
            // We need a fromArray/reconstruct method on ApprovalProof to be clean, 
            // or pass args to constructor via reflection if needed (it is final with private ctor).
            // But ApprovalProof is in Domain. Hydrator is Infrastructure.
            // Let's assume we can use reflection or add a static reconstruct method to ApprovalProof.
            // I'll assume reflection for now since it's used elsewhere here.
            
            $proofReflector = new ReflectionClass(\Domain\Shared\ValueObject\Proof\ApprovalProof::class);
            $proof = $proofReflector->newInstanceWithoutConstructor();
            
            $this->setProperty($proofReflector, $proof, 'proofId', $proofData['proof_id']);
            $this->setProperty($proofReflector, $proof, 'entityType', $proofData['entity_type']);
            $this->setProperty($proofReflector, $proof, 'entityId', $proofData['entity_id']);
            $this->setProperty($proofReflector, $proof, 'approvalType', $proofData['approval_type']);
            $this->setProperty($proofReflector, $proof, 'approverId', UserId::fromString($proofData['approver_id']));
            $this->setProperty($proofReflector, $proof, 'entityHash', \Domain\Shared\ValueObject\HashChain\ContentHash::fromString($proofData['entity_hash'])); // Assuming fromString works on hash val
            $this->setProperty($proofReflector, $proof, 'approvedAt', new DateTimeImmutable($proofData['approved_at']));
            $this->setProperty($proofReflector, $proof, 'notes', $proofData['notes'] ?? null);
            
            $this->setProperty($reflection, $approval, 'proof', $proof);
        } else {
            $this->setProperty($reflection, $approval, 'proof', null);
        }
        
        $this->setProperty($reflection, $approval, 'domainEvents', []);

        return $approval;
    }

    /**
     * Extract data from Approval entity for persistence.
     *
     * @return array<string, mixed>
     */
    public function extract(Approval $approval): array
    {
        return [
            'id' => $approval->id()->toString(),
            'company_id' => $approval->companyId()->toString(),
            'approval_type' => $approval->approvalType()->value,
            'entity_type' => $approval->entityType(),
            'entity_id' => $approval->entityId(),
            'reason' => $approval->reason()->description(),
            'requested_by' => $approval->requestedBy()->toString(),
            'requested_at' => $approval->requestedAt()->format('Y-m-d H:i:s'),
            'amount_cents' => $approval->amountCents(),
            'priority' => $approval->priority(),
            'expires_at' => $approval->expiresAt()?->format('Y-m-d H:i:s'),
            'status' => $approval->status()->value,
            'reviewed_by' => $approval->reviewedBy()?->toString(),
            'reviewed_at' => $approval->reviewedAt()?->format('Y-m-d H:i:s'),
            'review_notes' => $approval->reviewNotes(),
            'proof_json' => $approval->proof() ? json_encode($approval->proof()) : null,
        ];
    }

    /**
     * Set a property value using reflection.
     */
    private function setProperty(ReflectionClass $reflection, object $object, string $property, mixed $value): void
    {
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
