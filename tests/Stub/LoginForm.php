<?php

declare(strict_types=1);

namespace Yiisoft\Form\Tests\Stub;

use Yiisoft\Form\Tests\ValidatorFactoryMock;
use Yiisoft\Validator\Rule\Email;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\Rule\HasLength;
use Yiisoft\Form\FormModel;

class LoginForm extends FormModel
{
    private static ?string $extraField = null;
    private ?string $login = null;
    private ?string $password = null;
    private bool $rememberMe = false;

    public function __construct()
    {
        parent::__construct(new ValidatorFactoryMock());
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function getLoginAttribute(): ?string
    {
        return $this->login !== null ? 'app-' . $this->login : null;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getPasswordAttribute(bool $param): ?string
    {
        return 'wrong';
    }

    public function getRememberMe(): bool
    {
        return $this->rememberMe;
    }

    public function login(string $value): void
    {
        $this->login = $value;
    }

    public function setLoginAttribute(string $value): void
    {
        $this->login = 'app-' . $value;
    }

    public function password(string $value): void
    {
        $this->password = $value;
    }

    public function setPasswordAttribute(): void
    {
        $this->password = 'wrong';
    }

    public function rememberMe(bool $value): void
    {
        $this->rememberMe = $value;
    }

    public function attributeHints(): array
    {
        return [
            'login' => 'Write your id or email.',
            'password' => 'Write your password.'
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'login' => 'Login:',
            'password' => 'Password:',
            'rememberMe' => 'remember Me:'
        ];
    }

    protected function rules(): array
    {
        return [
            'login' => $this->loginRules(),
            'password' => $this->passwordRules()
        ];
    }

    private function loginRules(): array
    {
        return [
            new Required(),
            (new HasLength())
            ->min(4)
            ->max(40)
            ->tooShortMessage('Is too short.')
            ->tooLongMessage('Is too long.'),
            new Email()
        ];
    }

    private function passwordRules(): array
    {
        return [
            new Required(),
            (new HasLength())
            ->min(8)
            ->tooShortMessage('Is too short.'),
        ];
    }
}
