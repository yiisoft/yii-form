<?php

declare(strict_types=1);

namespace Yiisoft\Form\Tests\Html;

use Yiisoft\Form\Tests\TestCase;
use Yiisoft\Form\Tests\Stub\StubForm;
use Yiisoft\Form\Widget\TextInput;

final class TextInputTest extends TestCase
{
    public function testTextInput(): void
    {
        $form = new StubForm();

        $expected = '<input type="text" id="stubform-fieldstring" class="testMe" name="StubForm[fieldString]">';
        $created = TextInput::widget()
            ->form($form)
            ->attribute('fieldString')
            ->options(['class' => 'testMe'])
            ->required(false)
            ->run();
        $this->assertEquals($expected, $created);
    }

    public function testTextInputCustomPlaceholder(): void
    {
        $form = new StubForm();

        $expected = 'placeholder="Custom placeholder"';
        $created = TextInput::widget()
            ->form($form)
            ->attribute('fieldString')
            ->options(['placeholder' => 'Custom placeholder'])
            ->run();
        $this->assertStringContainsString($expected, $created);
    }

    public function testTextInputPlaceholderFillFromForm(): void
    {
        $form = new StubForm();

        $expected  = 'placeholder="Field String"';
        $created = TextInput::widget()
            ->form($form)
            ->attribute('fieldString')
            ->options(['placeholder' => true])
            ->run();
        $this->assertStringContainsString($expected, $created);
    }

    public function testTextInputPlaceholderFillFromModelTabular(): void
    {
        $form = new StubForm();

        $expected = 'placeholder="Field String"';
        $created = TextInput::widget()
            ->form($form)
            ->attribute('[0]fieldString')
            ->options(['placeholder' => true])
            ->run();
        $this->assertStringContainsString($expected, $created);
    }
}
