<?php

declare(strict_types=1);

namespace WicketAcc\Doctrine\Inflector\Rules\Esperanto;

use WicketAcc\Doctrine\Inflector\Rules\Pattern;
use WicketAcc\Doctrine\Inflector\Rules\Substitution;
use WicketAcc\Doctrine\Inflector\Rules\Transformation;
use WicketAcc\Doctrine\Inflector\Rules\Word;

class Inflectible
{
    /** @return Transformation[] */
    public static function getSingular(): iterable
    {
        yield new Transformation(new Pattern('oj$'), 'o');
    }

    /** @return Transformation[] */
    public static function getPlural(): iterable
    {
        yield new Transformation(new Pattern('o$'), 'oj');
    }

    /** @return Substitution[] */
    public static function getIrregular(): iterable
    {
        yield new Substitution(new Word(''), new Word(''));
    }
}
