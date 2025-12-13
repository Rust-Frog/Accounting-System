<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Transaction\ValueObject;

use Domain\Transaction\ValueObject\TransactionStatus;
use PHPUnit\Framework\TestCase;

final class TransactionStatusTest extends TestCase
{
    public function test_has_draft_status(): void
    {
        $status = TransactionStatus::DRAFT;
        $this->assertEquals('draft', $status->value);
    }

    public function test_has_posted_status(): void
    {
        $status = TransactionStatus::POSTED;
        $this->assertEquals('posted', $status->value);
    }

    public function test_has_voided_status(): void
    {
        $status = TransactionStatus::VOIDED;
        $this->assertEquals('voided', $status->value);
    }

    public function test_is_draft_returns_true_for_draft(): void
    {
        $this->assertTrue(TransactionStatus::DRAFT->isDraft());
        $this->assertFalse(TransactionStatus::POSTED->isDraft());
        $this->assertFalse(TransactionStatus::VOIDED->isDraft());
    }

    public function test_is_posted_returns_true_for_posted(): void
    {
        $this->assertTrue(TransactionStatus::POSTED->isPosted());
        $this->assertFalse(TransactionStatus::DRAFT->isPosted());
        $this->assertFalse(TransactionStatus::VOIDED->isPosted());
    }

    public function test_is_voided_returns_true_for_voided(): void
    {
        $this->assertTrue(TransactionStatus::VOIDED->isVoided());
        $this->assertFalse(TransactionStatus::DRAFT->isVoided());
        $this->assertFalse(TransactionStatus::POSTED->isVoided());
    }

    public function test_is_terminal_returns_true_for_voided(): void
    {
        $this->assertTrue(TransactionStatus::VOIDED->isTerminal());
        $this->assertFalse(TransactionStatus::DRAFT->isTerminal());
        $this->assertFalse(TransactionStatus::POSTED->isTerminal());
    }

    public function test_can_edit_returns_true_for_draft(): void
    {
        $this->assertTrue(TransactionStatus::DRAFT->canEdit());
        $this->assertFalse(TransactionStatus::POSTED->canEdit());
        $this->assertFalse(TransactionStatus::VOIDED->canEdit());
    }
}
