<?php

declare(strict_types=1);

namespace Yiisoft\Form;

use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;
use Yiisoft\Form\HtmlOptions\HtmlOptionsProvider;
use Yiisoft\Strings\Inflector;
use Yiisoft\Strings\StringHelper;
use Yiisoft\Validator\PostValidationHookInterface;
use Yiisoft\Validator\ResultSet;
use Yiisoft\Validator\Rule\Required;
use Yiisoft\Validator\RulesProviderInterface;
use function array_key_exists;
use function array_merge;
use function explode;
use function get_object_vars;
use function reset;
use function sprintf;
use function strpos;

/**
 * Form model represents an HTML form: its data, validation and presentation.
 */
abstract class FormModel implements FormModelInterface, PostValidationHookInterface, RulesProviderInterface
{
    /**
     * @var FormAttribute[]
     */
    private array $attributes = [];
    private ?Inflector $inflector = null;
    private bool $validated = false;

    public function __construct()
    {
        $this->attributes = $this->collectAttributes();
    }

    public function isAttributeRequired(string $attribute): bool
    {
        $validators = $this->getRules()[$attribute] ?? [];

        foreach ($validators as $validator) {
            if ($validator instanceof Required) {
                return true;
            }
            if ($validator instanceof HtmlOptionsProvider && (bool)($validator->getHtmlOptions()['required'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    public function getAttributeValue(string $attribute)
    {
        return $this->readProperty($attribute);
    }

    public function getAttributeLabels(): array
    {
        return [];
    }

    public function getAttributeHint(string $attribute): string
    {
        [$attribute, $nested] = $this->getNestedAttribute($attribute);
        if ($nested !== null) {
            $property = $this->readProperty($attribute);
            if ($property instanceof self) {
                return $property->getAttributeHint($nested);
            }

            return $property;
        }

        $hints = $this->getAttributeHints();

        return $hints[$attribute] ?? '';
    }

    public function getAttributeHints(): array
    {
        return [];
    }

    public function getAttributeLabel(string $attribute): string
    {
        if ($this->hasAttribute($attribute)) {
            return $this->getAttribute($attribute)->getLabel();
        }

        [$attribute, $nested] = $this->getNestedAttribute($attribute);

        if ($nested !== null) {
            $property = $this->readProperty($attribute);
            if ($property instanceof self) {
                return $property->getAttributeLabel($nested);
            }

            return $property;
        }

        return $this->generateAttributeLabel($attribute);
    }

    /**
     * @return string Returns classname without a namespace part or empty string when class is anonymous
     */
    public function getFormName(): string
    {
        if (strpos(static::class, '@anonymous') !== false) {
            return '';
        }

        $className = strrchr(static::class, '\\');
        if ($className === false) {
            return static::class;
        }

        return substr($className, 1);
    }

    public function getAttribute(string $attribute): FormAttribute
    {
        if (!$this->hasAttribute($attribute)) {
            throw new InvalidArgumentException("Attribute \"{$attribute}\" does not exist.");
        }
        return $this->attributes[$attribute];
    }

    public function hasAttribute(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }

    public function getError(string $attribute): array
    {
        return $this->getAttribute($attribute)->getErrors();
    }

    public function getErrors(): array
    {
        $result = [];
        foreach ($this->attributes as $attributeName => $attribute) {
            $errors = $attribute->getErrors();
            if ($errors !== []) {
                $result[$attributeName] = $errors;
            }
        }

        return $result;
    }

    public function getErrorSummary(bool $showAllErrors): array
    {
        $lines = [];
        $errors = $showAllErrors ? $this->getErrors() : [$this->getFirstErrors()];

        foreach ($errors as $error) {
            $lines = array_merge($lines, $error);
        }

        return $lines;
    }

    public function getFirstError(string $attribute): string
    {
        $errors = $this->getAttribute($attribute)->getErrors();
        if (empty($errors)) {
            return '';
        }

        return reset($errors);
    }

    public function getFirstErrors(): array
    {
        $result = [];
        foreach ($this->attributes as $attributeName => $attribute) {
            $errors = $attribute->getErrors();
            if ($errors !== []) {
                $result[$attributeName] = reset($errors);
            }
        }

        return $result;
    }

    public function hasErrors(?string $attribute = null): bool
    {
        if ($attribute !== null) {
            return $this->getAttribute($attribute)->getErrors() !== [];
        }

        /** @noinspection SuspiciousLoopInspection */
        foreach ($this->attributes as $attribute) {
            if ($attribute->getErrors() !== []) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array $data
     * @param string|null $formName
     *
     * @return bool
     */
    public function load(array $data, ?string $formName = null): bool
    {
        $scope = $formName ?? $this->getFormName();

        /**
         * @psalm-var array<string,mixed>
         */
        $values = [];

        if ($scope === '' && !empty($data)) {
            $values = $data;
        } elseif (isset($data[$scope])) {
            $values = $data[$scope];
        }

        foreach ($values as $name => $value) {
            $this->setAttribute($name, $value);
        }

        return $values !== [];
    }

    public function setAttribute(string $name, $value): void
    {
        [$realName] = $this->getNestedAttribute($name);
        if ($this->hasAttribute($realName)) {
            $attribute = $this->getAttribute($realName);
            switch ($attribute->getType()) {
                case 'bool':
                    $this->writeProperty($name, (bool) $value);
                    break;
                case 'float':
                    $this->writeProperty($name, (float) $value);
                    break;
                case 'int':
                    $this->writeProperty($name, (int) $value);
                    break;
                case 'string':
                    $this->writeProperty($name, (string) $value);
                    break;
                default:
                    $this->writeProperty($name, $value);
                    break;
            }
        }
    }

    public function processValidationResult(ResultSet $resultSet): void
    {
        $this->clearErrors();
        $this->validated = false;

        foreach ($resultSet as $attribute => $result) {
            if ($result->isValid() === false && ($errors = $result->getErrors()) !== []) {
                $this->getAttribute($attribute)->setErrors($errors);
            }
        }
        $this->validated = true;
    }

    public function getRules(): array
    {
        return [];
    }

    /**
     * Returns the list of attribute types indexed by attribute names.
     *
     * By default, this method returns all non-static properties of the class.
     *
     * @throws \ReflectionException
     *
     * @return array list of attribute types indexed by attribute names.
     */
    private function collectAttributes(): array
    {
        $class = new ReflectionClass($this);
        $attributes = [];

        $attributeLabels = $this->getAttributeLabels();
        $attributeHints = $this->getAttributeHints();

        foreach ($class->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            /** @var ReflectionNamedType $propertyType */
            $propertyType = $property->getType();
            $propertyName = $property->getName();
            if ($propertyType === null) {
                throw new InvalidArgumentException(sprintf(
                    'You must specify the type hint for "%s" property in "%s" class.',
                    $propertyName,
                    $property->getDeclaringClass()->getName(),
                ));
            }

            $attributes[$propertyName] = new FormAttribute(
                $propertyName,
                $propertyType->getName(),
                $attributeHints[$propertyName] ?? $this->getAttributeHint($propertyName),
                $attributeLabels[$propertyName] ?? $this->getAttributeLabel($propertyName),
            );
        }

        return $attributes;
    }

    private function clearErrors(): void
    {
        array_walk($this->attributes, static fn (FormAttribute $attribute) => $attribute->setErrors([]));
    }

    private function getInflector(): Inflector
    {
        if ($this->inflector === null) {
            $this->inflector = new Inflector();
        }
        return $this->inflector;
    }

    /**
     * Generates a user friendly attribute label based on the give attribute name.
     *
     * This is done by replacing underscores, dashes and dots with blanks and changing the first letter of each word to
     * upper case.
     *
     * For example, 'department_name' or 'DepartmentName' will generate 'Department Name'.
     *
     * @param string $name the column name.
     *
     * @return string the attribute label.
     */
    private function generateAttributeLabel(string $name): string
    {
        return StringHelper::uppercaseFirstCharacterInEachWord(
            $this->getInflector()->toWords($name)
        );
    }

    /**
     * @return FormAttribute|FormModel|string
     */
    private function readProperty(string $attribute)
    {
        $class = static::class;

        [$attribute, $nested] = $this->getNestedAttribute($attribute);

        if (!property_exists($class, $attribute)) {
            throw new InvalidArgumentException("Undefined property: \"$class::$attribute\".");
        }

        if ($this->isPublicAttribute($attribute)) {
            return $nested === null ? $this->$attribute : $this->$attribute->getAttributeValue($nested);
        }

        $getter = static fn (FormModel $class, $attribute) => $nested === null
            ? $class->$attribute
            : $class->$attribute->getAttributeValue($nested);
        $getter = Closure::bind($getter, null, $this);

        /**
         * @psalm-var Closure<string|FormAttribute|FormModel> $getter
         */
        return $getter($this, $attribute);
    }

    private function writeProperty(string $attribute, $value): void
    {
        [$attribute, $nested] = $this->getNestedAttribute($attribute);
        if ($this->isPublicAttribute($attribute)) {
            if ($nested === null) {
                $this->$attribute = $value;
            } else {
                $this->$attribute->setAttribute($attribute, $value);
            }
        } else {
            $setter = fn (FormModel $class, $attribute, $value) => $nested === null
                ? $class->$attribute = $value
                : $class->$attribute->setAttribute($nested, $value);
            $setter = Closure::bind($setter, null, $this);

            /**
             * @psalm-var Closure $setter
             */
            $setter($this, $attribute, $value);
        }
    }

    private function isPublicAttribute(string $attribute): bool
    {
        return array_key_exists($attribute, get_object_vars($this));
    }

    private function getNestedAttribute(string $attribute): array
    {
        if (strpos($attribute, '.') === false) {
            return [$attribute, null];
        }

        [$attribute, $nested] = explode('.', $attribute, 2);

        $nestedAttribute = $this->getAttribute($attribute);
        if (!($nestedAttribute instanceof FormAttribute)) {
            throw new InvalidArgumentException(sprintf(
                'Nested attribute can only be of %s or %s types, type %s got.',
                FormAttribute::class,
                self::class,
                is_object($nestedAttribute) ? get_class($nestedAttribute) : gettype($nestedAttribute)
            ));
        }

        return [$attribute, $nested];
    }

    public function isValidated(): bool
    {
        return $this->validated;
    }
}
