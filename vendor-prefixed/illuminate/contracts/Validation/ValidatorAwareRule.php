<?php

namespace WicketAcc\Illuminate\Contracts\Validation;

use Illuminate\Validation\Validator;

interface ValidatorAwareRule
{
    /**
     * Set the current validator.
     *
     * @param  Validator  $validator
     * @return $this
     */
    public function setValidator(Validator $validator);
}
