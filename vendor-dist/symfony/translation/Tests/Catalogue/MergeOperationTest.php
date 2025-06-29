<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WicketAcc\Symfony\Component\Translation\Tests\Catalogue;

use WicketAcc\Symfony\Component\Translation\Catalogue\MergeOperation;
use WicketAcc\Symfony\Component\Translation\MessageCatalogue;
use WicketAcc\Symfony\Component\Translation\MessageCatalogueInterface;

class MergeOperationTest extends AbstractOperationTestCase
{
    public function testGetMessagesFromSingleDomain()
    {
        $operation = $this->createOperation(
            new MessageCatalogue('en', ['messages' => ['a' => 'old_a', 'b' => 'old_b']]),
            new MessageCatalogue('en', ['messages' => ['a' => 'new_a', 'c' => 'new_c']])
        );

        $this->assertEquals(
            ['a' => 'old_a', 'b' => 'old_b', 'c' => 'new_c'],
            $operation->getMessages('messages')
        );

        $this->assertEquals(
            ['c' => 'new_c'],
            $operation->getNewMessages('messages')
        );

        $this->assertEquals(
            [],
            $operation->getObsoleteMessages('messages')
        );
    }

    public function testGetResultFromSingleDomain()
    {
        $this->assertEquals(
            new MessageCatalogue('en', [
                'messages' => ['a' => 'old_a', 'b' => 'old_b', 'c' => 'new_c'],
            ]),
            $this->createOperation(
                new MessageCatalogue('en', ['messages' => ['a' => 'old_a', 'b' => 'old_b']]),
                new MessageCatalogue('en', ['messages' => ['a' => 'new_a', 'c' => 'new_c']])
            )->getResult()
        );
    }

    public function testGetResultFromIntlDomain()
    {
        $this->assertEquals(
            new MessageCatalogue('en', [
                'messages' => ['b' => 'old_b'],
                'messages+intl-icu' => ['d' => 'old_d', 'c' => 'new_c', 'a' => 'new_a'],
            ]),
            $this->createOperation(
                new MessageCatalogue('en', ['messages' => ['a' => 'old_a', 'b' => 'old_b'], 'messages+intl-icu' => ['d' => 'old_d']]),
                new MessageCatalogue('en', ['messages+intl-icu' => ['a' => 'new_a', 'c' => 'new_c']])
            )->getResult()
        );
    }

    public function testGetResultWithMetadata()
    {
        $leftCatalogue = new MessageCatalogue('en', ['messages' => ['a' => 'old_a', 'b' => 'old_b']]);
        $leftCatalogue->setMetadata('a', 'foo', 'messages');
        $leftCatalogue->setMetadata('b', 'bar', 'messages');
        $rightCatalogue = new MessageCatalogue('en', ['messages' => ['b' => 'new_b', 'c' => 'new_c']]);
        $rightCatalogue->setMetadata('b', 'baz', 'messages');
        $rightCatalogue->setMetadata('c', 'qux', 'messages');

        $mergedCatalogue = new MessageCatalogue('en', ['messages' => ['a' => 'old_a', 'b' => 'old_b', 'c' => 'new_c']]);
        $mergedCatalogue->setMetadata('a', 'foo', 'messages');
        $mergedCatalogue->setMetadata('b', 'bar', 'messages');
        $mergedCatalogue->setMetadata('c', 'qux', 'messages');

        $this->assertEquals(
            $mergedCatalogue,
            $this->createOperation(
                $leftCatalogue,
                $rightCatalogue
            )->getResult()
        );
    }

    public function testGetResultWithMetadataFromIntlDomain()
    {
        $leftCatalogue = new MessageCatalogue('en', ['messages+intl-icu' => ['a' => 'old_a', 'b' => 'old_b']]);
        $leftCatalogue->setMetadata('a', 'foo', 'messages+intl-icu');
        $leftCatalogue->setMetadata('b', 'bar', 'messages+intl-icu');
        $rightCatalogue = new MessageCatalogue('en', ['messages+intl-icu' => ['b' => 'new_b', 'c' => 'new_c']]);
        $rightCatalogue->setMetadata('b', 'baz', 'messages+intl-icu');
        $rightCatalogue->setMetadata('c', 'qux', 'messages+intl-icu');

        $mergedCatalogue = new MessageCatalogue('en', ['messages+intl-icu' => ['a' => 'old_a', 'b' => 'old_b', 'c' => 'new_c']]);
        $mergedCatalogue->setMetadata('a', 'foo', 'messages+intl-icu');
        $mergedCatalogue->setMetadata('b', 'bar', 'messages+intl-icu');
        $mergedCatalogue->setMetadata('c', 'qux', 'messages+intl-icu');

        $this->assertEquals(
            $mergedCatalogue,
            $this->createOperation(
                $leftCatalogue,
                $rightCatalogue
            )->getResult()
        );
    }

    protected function createOperation(MessageCatalogueInterface $source, MessageCatalogueInterface $target)
    {
        return new MergeOperation($source, $target);
    }
}
