<?php

namespace Carbon_Fields\Field;

/**
 * Textarea field class.
 */
class Textarea_Field extends Field
{
    /**
     * @inheritDoc
     */
    protected $allowed_attributes = ['maxLength', 'minLength', 'placeholder', 'readOnly', 'is', 'autocomplete'];

    /**
     * Number of rows (affects textarea height).
     *
     * @var int
     */
    protected $rows = 5;

    /**
     * Change the number of rows of this field.
     *
     * @param  int $rows Number of rows
     * @return self    $this
     */
    public function set_rows($rows = 0)
    {
        $this->rows = absint($rows);

        return $this;
    }

    /**
     * Returns an array that holds the field data, suitable for JSON representation.
     *
     * @param  bool  $load Should the value be loaded from the database or use the value from the current instance.
     * @return array
     */
    public function to_json($load)
    {
        $field_data = parent::to_json($load);

        $field_data = array_merge($field_data, [
            'rows' => $this->rows,
        ]);

        return $field_data;
    }
}
