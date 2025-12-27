<?php

declare(strict_types=1);

namespace Domain\Audit\ValueObject;

/**
 * Value object representing a single field change.
 */
final readonly class ChangeRecord
{
    private function __construct(
        private string $field,
        private mixed $previousValue,
        private mixed $newValue,
        private string $changeType
    ) {
    }

    public static function added(string $field, mixed $value): self
    {
        return new self($field, null, $value, 'added');
    }

    public static function removed(string $field, mixed $previousValue): self
    {
        return new self($field, $previousValue, null, 'removed');
    }

    public static function modified(string $field, mixed $previousValue, mixed $newValue): self
    {
        return new self($field, $previousValue, $newValue, 'modified');
    }

    public function field(): string
    {
        return $this->field;
    }

    public function previousValue(): mixed
    {
        return $this->previousValue;
    }

    public function newValue(): mixed
    {
        return $this->newValue;
    }

    public function changeType(): string
    {
        return $this->changeType;
    }

    public function isAddition(): bool
    {
        return $this->changeType === 'added';
    }

    public function isRemoval(): bool
    {
        return $this->changeType === 'removed';
    }

    public function isModification(): bool
    {
        return $this->changeType === 'modified';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'previous_value' => $this->previousValue,
            'new_value' => $this->newValue,
            'change_type' => $this->changeType,
        ];
    }
}
