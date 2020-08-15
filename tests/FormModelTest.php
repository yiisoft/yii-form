<?php

declare(strict_types=1);

namespace Yiisoft\Form\Tests;

use InvalidArgumentException;
use Yiisoft\Form\FormModel;
use Yiisoft\Form\Tests\Stub\LoginForm;

use Yiisoft\Validator\ValidatorFactoryInterface;

use function str_repeat;

final class FormModelTest extends TestCase
{
    public function testAnonymousFormName(): void
    {
        $form = new class(new ValidatorFactoryMock()) extends FormModel {};
        $this->assertEquals('', $form->formName());
    }

    public function testDefaultFormName(): void
    {
        $form = new DefaultFormNameForm();
        $this->assertEquals('DefaultFormNameForm', $form->formName());
    }

    public function testCustomFormName(): void
    {
        $form = new CustomFormNameForm();
        $this->assertEquals('my-best-form-name', $form->formName());
    }

    public function testUnknownPropertyType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(sprintf(
            '/You must specify the type hint for "%s" property in "([^"]+)" class./',
            'property',
        ));
        $form = new class(new ValidatorFactoryMock()) extends FormModel{
            private $property;
        };
    }

    public function testGetAttributeValue(): void
    {
        $form = new LoginForm();

        $form->login('admin');
        $this->assertEquals('app-admin', $form->getAttributeValue('login'));

        $form->password('123456');
        $this->assertEquals('123456', $form->getAttributeValue('password'));

        $form->rememberMe(true);
        $this->assertEquals(true, $form->getAttributeValue('rememberMe'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Undefined property: "Yiisoft\Form\Tests\Stub\LoginForm::noExist".');
        $form->getAttributeValue('noExist');
    }

    public function testGetAttributeHint(): void
    {
        $form = new LoginForm();

        $this->assertEquals('Write your id or email.', $form->attributeHint('login'));
        $this->assertEquals('Write your password.', $form->attributeHint('password'));
        $this->assertEmpty($form->attributeHint('noExist'));
    }

    public function testGetAttributeLabel(): void
    {
        $form = new LoginForm();

        $this->assertEquals('Login:', $form->attributeLabel('login'));
        $this->assertEquals('Testme', $form->attributeLabel('testme'));
    }

    public function testAttributesLabels(): void
    {
        $form = new LoginForm();

        $expected = [
            'login' => 'Login:',
            'password' => 'Password:',
            'rememberMe' => 'remember Me:',
        ];

        $this->assertEquals($expected, $form->attributeLabels());
    }

    public function testErrorSummary(): void
    {
        $form = new LoginForm();

        $data = [
            'LoginForm' => [
                'login' => 'admin@.com',
                'password' => '123456',
            ],
        ];

        $expected = [
            'login' => 'This value is not a valid email address.',
            'password' => 'Is too short.',
        ];

        $this->assertTrue($form->load($data));
        $this->assertFalse($form->validate());

        $this->assertEquals(
            $expected,
            $form->errorSummary(false)
        );

        $expected = [
            'This value is not a valid email address.',
            'Is too short.',
        ];

        $this->assertEquals(
            $expected,
            $form->errorSummary(true)
        );
    }

    public function testHasAttribute(): void
    {
        $form = new LoginForm();

        $this->assertTrue($form->hasAttribute('login'));
        $this->assertTrue($form->hasAttribute('password'));
        $this->assertTrue($form->hasAttribute('rememberMe'));
        $this->assertFalse($form->hasAttribute('noExist'));
        $this->assertFalse($form->hasAttribute('extraField'));
    }

    public function testLoad(): void
    {
        $form = new LoginForm();

        $this->assertNull($form->getLogin());
        $this->assertNull($form->getPassword());
        $this->assertFalse($form->getRememberMe());

        $data = [
            'LoginForm' => [
                'login' => 'admin',
                'password' => '123456',
                'rememberMe' => true,
                'noExist' => 'noExist',
            ],
        ];

        $this->assertTrue($form->load($data));

        $this->assertEquals('app-admin', $form->getLogin());
        $this->assertEquals('123456', $form->getPassword());
        $this->assertEquals(true, $form->getRememberMe());
    }

    public function testSetterAndGetterForAttributes(): void
    {
        $form = new LoginForm();

        $data = [
            'LoginForm' => [
                'login' => 'admin',
            ],
        ];

        $this->assertTrue($form->load($data));

        $this->assertEquals('app-admin', $form->getLogin());

        $form->login('user');

        $this->assertEquals('app-user', $form->getAttributeValue('login'));
    }

    public function testSetterAndGetterIsIgnoredWhenIncorrectNumberOfParameters(): void
    {
        $form = new LoginForm();

        $data = [
            'LoginForm' => [
                'password' => '1234',
            ],
        ];

        $this->assertTrue($form->load($data));

        $this->assertEquals('1234', $form->getPassword());

        $form->password('123456');

        $this->assertEquals('123456', $form->getAttributeValue('password'));
    }

    public function testFailedLoadForm(): void
    {
        $form1 = new LoginForm();
        $form2 = new class(new ValidatorFactoryMock()) extends FormModel{
        };

        $data1 = [
            'LoginForm2' => [
                'login' => 'admin',
                'password' => '123456',
                'rememberMe' => true,
                'noExist' => 'noExist',
            ],
        ];
        $data2 = [];

        $this->assertFalse($form1->load($data1));
        $this->assertFalse($form1->load($data2));

        $this->assertTrue($form2->load($data1));
        $this->assertFalse($form2->load($data2));
    }

    public function testSetAttributes()
    {
        $form = new class(new ValidatorFactoryMock()) extends FormModel{
            private int $int = 1;
            private string $string = 'string';
            private float $float = 3.14;
            private bool $bool = true;
        };
        $form->setAttributes([
            'int' => '2',
            'float' => '3.15',
            'bool' => 'false',
            'string' => 555,
        ]);
        $this->assertIsInt($form->getAttributeValue('int'));
        $this->assertIsFloat($form->getAttributeValue('float'));
        $this->assertIsBool($form->getAttributeValue('bool'));
        $this->assertIsString($form->getAttributeValue('string'));
    }

    public function testAddError(): void
    {
        $form = new LoginForm();
        $errorMessage = 'Invalid password.';

        $form->addError('password', $errorMessage);

        $this->assertTrue($form->hasErrors());
        $this->assertEquals($errorMessage, $form->firstError('password'));
    }

    public function testAddAndGetErrorForNonExistingAttribute(): void
    {
        $form = new LoginForm();
        $errorMessage = 'Invalid username and/or password.';

        $form->addError('form', $errorMessage);

        $this->assertTrue($form->hasErrors());
        $this->assertEquals($errorMessage, $form->firstError('form'));
    }

    public function testValidatorRules(): void
    {
        $form = new LoginForm();

        $form->password('');
        $form->validate();

        $this->assertEquals(
            ['Value cannot be blank.'],
            $form->error('password')
        );

        $form->password('x');
        $form->validate();
        $this->assertEquals(
            ['Is too short.'],
            $form->error('password')
        );

        $form->login(str_repeat('x', 60));
        $form->validate();
        $this->assertEquals(
            'Is too long.',
            $form->firstError('login')
        );

        $form->login('admin@.com');
        $form->validate();
        $this->assertEquals(
            'This value is not a valid email address.',
            $form->firstError('login')
        );
    }
}

final class DefaultFormNameForm extends FormModel
{
    public function __construct()
    {
        parent::__construct(new ValidatorFactoryMock());
    }
}

final class CustomFormNameForm extends FormModel
{
    public function __construct()
    {
        parent::__construct(new ValidatorFactoryMock());
    }

    public function formName(): string
    {
        return 'my-best-form-name';
    }
}
