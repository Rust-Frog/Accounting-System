<?php

declare(strict_types=1);

namespace Domain\Transaction\Entity;

use DateTimeImmutable;
use Domain\ChartOfAccounts\ValueObject\AccountId;
use Domain\Company\ValueObject\CompanyId;
use Domain\Identity\ValueObject\UserId;
use Domain\Shared\Event\DomainEvent;
use Domain\Shared\Exception\BusinessRuleException;
use Domain\Shared\ValueObject\Currency;
use Domain\Shared\ValueObject\Money;
use Domain\Transaction\Event\TransactionCreated;
use Domain\Transaction\Event\TransactionPosted;
use Domain\Transaction\Event\TransactionVoided;
use Domain\Transaction\ValueObject\LineType;
use Domain\Transaction\ValueObject\TransactionId;
use Domain\Transaction\ValueObject\TransactionStatus;

final class Transaction
{
    /** @var array<TransactionLine> */
    private array $lines = [];

    /** @var array<DomainEvent> */
    private array $domainEvents = [];

    private ?DateTimeImmutable $postedAt = null;
    private ?UserId $postedBy = null;
    private ?DateTimeImmutable $voidedAt = null;
    private ?UserId $voidedBy = null;
    private ?string $voidReason = null;

    private function __construct(
        private readonly TransactionId $id,
        private readonly CompanyId $companyId,
        private readonly DateTimeImmutable $transactionDate,
        private readonly string $description,
        private readonly UserId $createdBy,
        private readonly DateTimeImmutable $createdAt,
        private TransactionStatus $status,
        private readonly ?string $referenceNumber,
    ) {
    }

    public static function create(
        CompanyId $companyId,
        DateTimeImmutable $transactionDate,
        string $description,
        UserId $createdBy,
        ?string $referenceNumber = null,
    ): self {
        $id = TransactionId::generate();
        $createdAt = new DateTimeImmutable();

        $transaction = new self(
            id: $id,
            companyId: $companyId,
            transactionDate: $transactionDate,
            description: $description,
            createdBy: $createdBy,
            createdAt: $createdAt,
            status: TransactionStatus::DRAFT,
            referenceNumber: $referenceNumber,
        );

        $transaction->recordEvent(new TransactionCreated(
            transactionId: $id->toString(),
            companyId: $companyId->toString(),
            description: $description,
            createdBy: $createdBy->toString(),
            occurredAt: $createdAt,
        ));

        return $transaction;
    }

    public function addLine(
        AccountId $accountId,
        LineType $lineType,
        Money $amount,
        ?string $description = null,
    ): void {
        $this->ensureCanModify();

        $line = TransactionLine::create(
            accountId: $accountId,
            lineType: $lineType,
            amount: $amount,
            description: $description,
        );

        $this->lines[] = $line;
    }

    public function post(UserId $postedBy): void
    {
        $this->ensureCanModify();
        $this->validateForPosting();

        $this->status = TransactionStatus::POSTED;
        $this->postedAt = new DateTimeImmutable();
        $this->postedBy = $postedBy;

        $this->recordEvent(new TransactionPosted(
            transactionId: $this->id->toString(),
            companyId: $this->companyId->toString(),
            postedBy: $postedBy->toString(),
            occurredAt: $this->postedAt,
        ));
    }

    public function void(string $reason, UserId $voidedBy): void
    {
        if ($this->status->isVoided()) {
            throw new BusinessRuleException('Voided transactions cannot be modified');
        }

        if (!$this->status->isPosted()) {
            throw new BusinessRuleException('Only posted transactions can be voided');
        }

        $this->status = TransactionStatus::VOIDED;
        $this->voidReason = $reason;
        $this->voidedAt = new DateTimeImmutable();
        $this->voidedBy = $voidedBy;

        $this->recordEvent(new TransactionVoided(
            transactionId: $this->id->toString(),
            companyId: $this->companyId->toString(),
            voidReason: $reason,
            voidedBy: $voidedBy->toString(),
            occurredAt: $this->voidedAt,
        ));
    }

    public function id(): TransactionId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function transactionDate(): DateTimeImmutable
    {
        return $this->transactionDate;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function createdBy(): UserId
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function status(): TransactionStatus
    {
        return $this->status;
    }

    public function referenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function postedAt(): ?DateTimeImmutable
    {
        return $this->postedAt;
    }

    public function postedBy(): ?UserId
    {
        return $this->postedBy;
    }

    public function voidedAt(): ?DateTimeImmutable
    {
        return $this->voidedAt;
    }

    public function voidedBy(): ?UserId
    {
        return $this->voidedBy;
    }

    public function voidReason(): ?string
    {
        return $this->voidReason;
    }

    /**
     * @return array<TransactionLine>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    public function lineCount(): int
    {
        return count($this->lines);
    }

    public function isDraft(): bool
    {
        return $this->status->isDraft();
    }

    public function isPosted(): bool
    {
        return $this->status->isPosted();
    }

    public function isVoided(): bool
    {
        return $this->status->isVoided();
    }

    public function totalDebits(): Money
    {
        $total = 0;
        foreach ($this->lines as $line) {
            if ($line->isDebit()) {
                $total += $line->amount()->cents();
            }
        }

        return Money::fromCents($total, Currency::PHP);
    }

    public function totalCredits(): Money
    {
        $total = 0;
        foreach ($this->lines as $line) {
            if ($line->isCredit()) {
                $total += $line->amount()->cents();
            }
        }

        return Money::fromCents($total, Currency::PHP);
    }

    public function isBalanced(): bool
    {
        return $this->totalDebits()->cents() === $this->totalCredits()->cents();
    }

    public function hasDebitLines(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->isDebit()) {
                return true;
            }
        }

        return false;
    }

    public function hasCreditLines(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->isCredit()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toContentArray(): array
    {
        $linesData = [];
        foreach ($this->lines as $line) {
            $linesData[] = [
                'account_id' => $line->accountId()->toString(),
                'line_type' => $line->lineType()->value,
                'amount_cents' => $line->amount()->cents(),
                'description' => $line->description(),
            ];
        }
        
        // Sort lines by something deterministic? 
        // For now, rely on insertion order as critical content.
        
        return [
            'id' => $this->id->toString(),
            'company_id' => $this->companyId->toString(),
            'transaction_date' => $this->transactionDate->format('Y-m-d'),
            'description' => $this->description,
            'reference_number' => $this->referenceNumber,
            'lines' => $linesData,
        ];
    }

    private function ensureCanModify(): void
    {
        if ($this->status->isVoided()) {
            throw new BusinessRuleException('Voided transactions cannot be modified');
        }

        if ($this->status->isPosted()) {
            throw new BusinessRuleException('Posted transactions cannot be modified');
        }
    }

    private function validateForPosting(): void
    {
        if (count($this->lines) < 2) {
            throw new BusinessRuleException('Transaction must have at least 2 lines');
        }

        if (!$this->hasDebitLines() || !$this->hasCreditLines()) {
            throw new BusinessRuleException('Transaction must have at least one debit and one credit');
        }

        if (!$this->isBalanced()) {
            throw new BusinessRuleException(
                sprintf(
                    'Transaction is not balanced: Debits (%d) != Credits (%d)',
                    $this->totalDebits()->cents(),
                    $this->totalCredits()->cents()
                )
            );
        }
    }

    private function recordEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * @return array<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
