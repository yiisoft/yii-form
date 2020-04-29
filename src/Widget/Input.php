<?php

declare(strict_types=1);

namespace Yiisoft\Form\Widget;

use Yiisoft\Html\Html;
use Yiisoft\Widget\Widget;

final class Input extends Widget
{
    use Collection\Options;
    use Collection\InputOptions;

    private string $type;

    /**
     * Generates an input tag for the given form attribute.
     *
     * @return string the generated input tag.
     */
    public function run(): string
    {
        $new = clone $this;

        $new->placeholderOptions($new);

        if (!empty($new->addId())) {
            $new->options['id'] = $new->addId();
        }

        return Html::input($new->type, $new->addName(), $new->addValue(), $new->options);
    }

    public function type(string $value): self
    {
        $new = clone $this;
        $new->type = $value;
        return $new;
    }
}
