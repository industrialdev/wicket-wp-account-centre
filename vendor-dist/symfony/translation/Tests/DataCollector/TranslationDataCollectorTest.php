<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WicketAcc\Symfony\Component\Translation\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WicketAcc\Symfony\Component\Translation\DataCollector\TranslationDataCollector;
use WicketAcc\Symfony\Component\Translation\DataCollectorTranslator;

class TranslationDataCollectorTest extends TestCase
{
    public function testCollectEmptyMessages()
    {
        $translator = $this->getTranslator();
        $translator->expects($this->any())->method('getCollectedMessages')->willReturn([]);

        $dataCollector = new TranslationDataCollector($translator);
        $dataCollector->lateCollect();

        $this->assertEquals(0, $dataCollector->getCountMissings());
        $this->assertEquals(0, $dataCollector->getCountFallbacks());
        $this->assertEquals(0, $dataCollector->getCountDefines());
        $this->assertEquals([], $dataCollector->getMessages()->getValue());
    }

    public function testCollect()
    {
        $collectedMessages = [
            [
                'id' => 'foo',
                'translation' => 'foo (en)',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_DEFINED,
                'parameters' => [],
                'transChoiceNumber' => null,
            ],
            [
                'id' => 'bar',
                'translation' => 'bar (fr)',
                'locale' => 'fr',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
                'parameters' => [],
                'transChoiceNumber' => null,
            ],
            [
                'id' => 'choice',
                'translation' => 'choice',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_MISSING,
                'parameters' => ['%count%' => 3],
                'transChoiceNumber' => 3,
            ],
            [
                'id' => 'choice',
                'translation' => 'choice',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_MISSING,
                'parameters' => ['%count%' => 3],
                'transChoiceNumber' => 3,
            ],
            [
                'id' => 'choice',
                'translation' => 'choice',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_MISSING,
                'parameters' => ['%count%' => 4, '%foo%' => 'bar'],
                'transChoiceNumber' => 4,
            ],
        ];
        $expectedMessages = [
            [
                'id' => 'foo',
                'translation' => 'foo (en)',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_DEFINED,
                'count' => 1,
                'parameters' => [],
                'transChoiceNumber' => null,
            ],
            [
                'id' => 'bar',
                'translation' => 'bar (fr)',
                'locale' => 'fr',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_EQUALS_FALLBACK,
                'count' => 1,
                'parameters' => [],
                'transChoiceNumber' => null,
            ],
            [
                'id' => 'choice',
                'translation' => 'choice',
                'locale' => 'en',
                'domain' => 'messages',
                'state' => DataCollectorTranslator::MESSAGE_MISSING,
                'count' => 3,
                'parameters' => [
                    ['%count%' => 3],
                    ['%count%' => 3],
                    ['%count%' => 4, '%foo%' => 'bar'],
                ],
                'transChoiceNumber' => 3,
            ],
        ];

        $translator = $this->getTranslator();
        $translator->expects($this->any())->method('getCollectedMessages')->willReturn($collectedMessages);

        $dataCollector = new TranslationDataCollector($translator);
        $dataCollector->lateCollect();

        $this->assertEquals(1, $dataCollector->getCountMissings());
        $this->assertEquals(1, $dataCollector->getCountFallbacks());
        $this->assertEquals(1, $dataCollector->getCountDefines());

        $this->assertEquals($expectedMessages, array_values($dataCollector->getMessages()->getValue(true)));
    }

    public function testCollectAndReset()
    {
        $translator = $this->getTranslator();
        $translator->method('getLocale')->willReturn('fr');
        $translator->method('getFallbackLocales')->willReturn(['en']);

        $dataCollector = new TranslationDataCollector($translator);
        $dataCollector->collect($this->createMock(Request::class), $this->createMock(Response::class));

        $this->assertSame('fr', $dataCollector->getLocale());
        $this->assertSame(['en'], $dataCollector->getFallbackLocales());

        $dataCollector->reset();

        $this->assertNull($dataCollector->getLocale());
        $this->assertEmpty($dataCollector->getFallbackLocales());
    }

    private function getTranslator()
    {
        $translator = $this
            ->getMockBuilder(DataCollectorTranslator::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        return $translator;
    }
}
