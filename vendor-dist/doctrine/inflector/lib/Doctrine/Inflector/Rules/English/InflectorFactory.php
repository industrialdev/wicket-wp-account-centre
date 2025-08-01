<?php

declare(strict_types=1);

namespace WicketAcc\Doctrine\Inflector\Rules\English;

use WicketAcc\Doctrine\Inflector\GenericLanguageInflectorFactory;
use WicketAcc\Doctrine\Inflector\Rules\Ruleset;

final class InflectorFactory extends GenericLanguageInflectorFactory
{
    protected function getSingularRuleset(): Ruleset
    {
        return Rules::getSingularRuleset();
    }

    protected function getPluralRuleset(): Ruleset
    {
        return Rules::getPluralRuleset();
    }
}
