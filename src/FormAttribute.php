<?php

declare(strict_types=1);

namespace Yiisoft\Form;

class FormAttribute
{
    private string $name;
    private ?string $type;
    private string $hint;
    private string $label;
    private array $errors;

    public function __construct(string $name, ?string $type, string $hint, string $label)
    {
        $this->name = $name;
        $this->type = $type;
        $this->hint = $hint;
        $this->label = $label;
        $this->errors = [];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getHint(): string
    {
        return $this->hint;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }
}
