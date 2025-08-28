<?php

declare(strict_types=1);

namespace WicketAcc\Doctrine\Inflector\Rules;

use function strtolower;
use function strtoupper;
use function substr;

use WicketAcc\Doctrine\Inflector\WordInflector;

class Substitutions implements WordInflector
{
    /** @var Substitution[] */
    private $substitutions;

    public function __construct(Substitution ...$substitutions)
    {
        foreach ($substitutions as $substitution) {
            $this->substitutions[$substitution->getFrom()->getWord()] = $substitution;
        }
    }

    public function getFlippedSubstitutions(): self
    {
        $substitutions = [];

        foreach ($this->substitutions as $substitution) {
            $substitutions[] = new Substitution(
                $substitution->getTo(),
                $substitution->getFrom()
            );
        }

        return new self(...$substitutions);
    }

    public function inflect(string $word): string
    {
        $lowerWord = strtolower($word);

        if (isset($this->substitutions[$lowerWord])) {
            $firstLetterUppercase = $lowerWord[0] !== $word[0];

            $toWord = $this->substitutions[$lowerWord]->getTo()->getWord();

            if ($firstLetterUppercase) {
                return strtoupper($toWord[0]) . substr($toWord, 1);
            }

            return $toWord;
        }

        return $word;
    }
}
