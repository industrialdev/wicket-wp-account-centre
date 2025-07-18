<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WicketAcc\Symfony\Component\Translation\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use WicketAcc\Symfony\Component\Translation\Exception\InvalidResourceException;
use WicketAcc\Symfony\Component\Translation\Exception\NotFoundResourceException;
use WicketAcc\Symfony\Component\Translation\Loader\PhpFileLoader;

class PhpFileLoaderTest extends TestCase
{
    public function testLoad()
    {
        $loader = new PhpFileLoader();
        $resource = __DIR__.'/../Fixtures/resources.php';
        $catalogue = $loader->load($resource, 'en', 'domain1');

        $this->assertEquals(['foo' => 'bar'], $catalogue->all('domain1'));
        $this->assertEquals('en', $catalogue->getLocale());
        $this->assertEquals([new FileResource($resource)], $catalogue->getResources());
    }

    public function testLoadNonExistingResource()
    {
        $this->expectException(NotFoundResourceException::class);
        $loader = new PhpFileLoader();
        $resource = __DIR__.'/../Fixtures/non-existing.php';
        $loader->load($resource, 'en', 'domain1');
    }

    public function testLoadThrowsAnExceptionIfFileNotLocal()
    {
        $this->expectException(InvalidResourceException::class);
        $loader = new PhpFileLoader();
        $resource = 'http://example.com/resources.php';
        $loader->load($resource, 'en', 'domain1');
    }
}
