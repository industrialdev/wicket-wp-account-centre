<?php

namespace Carbon_Fields\Field;

/**
 * Text field class.
 */
class Text_Field extends Field
{
    /**
     * @inheritDoc
     */
    protected $allowed_attributes = ['list', 'max', 'maxLength', 'min', 'pattern', 'placeholder', 'readOnly', 'step', 'type', 'is', 'inputmode', 'autocomplete'];
}
