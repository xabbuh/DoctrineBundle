<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\DbalTestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;

use function array_intersect_key;

class UrlOverrideTest extends TestCase
{
    /**
     * @param array<string, (bool|string|null)> $config
     * @param array<string, (bool|string|null)> $expectedParams
     */
    #[IgnoreDeprecations]
    #[DataProvider('connectionDataProvider')]
    public function testConnectionConfiguration(array $config, array $expectedParams): void
    {
        $kernel = new DbalTestKernel($config);
        $kernel->boot();

        self::assertEquals(
            $expectedParams,
            array_intersect_key(
                $kernel->getContainer()->get('doctrine.dbal.default_connection')->getParams(),
                $expectedParams,
            ),
        );
    }

    /** @return array<string, array{0: array<string, (bool|string|null)>, 1:  array<string, (bool|string|null)>}> */
    public static function connectionDataProvider(): array
    {
        return [
            'override some' => [
                [
                    'override_url' => true,
                    'url' => 'mysql://database/main',
                    'password' => 'wordPass',
                    'host' => '127.0.0.1',
                ],
                [
                    'user' => 'root',
                    'password' => 'wordPass',
                    'host' => '127.0.0.1',
                    'port' => null,
                    'dbname' => 'main',
                ],
            ],
            'override with same value as in URL' => [
                [
                    'override_url' => true,
                    'url' => 'mysql://someone@database/main',
                    'user' => 'someone',
                ],
                [
                    'user' => 'someone',
                    'password' => null,
                    'host' => 'database',
                    'port' => null,
                    'dbname' => 'main',
                ],
            ],
            'nothing to override' => [
                [
                    'override_url' => true,
                    'url' => 'mysql://database/main',
                ],
                [
                    'user' => 'root',
                    'password' => null,
                    'host' => 'database',
                    'port' => null,
                    'dbname' => 'main',
                ],
            ],
        ];
    }
}
