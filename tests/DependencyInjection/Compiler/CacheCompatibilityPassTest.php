<?php

namespace Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Compiler;

use Doctrine\Bundle\DoctrineBundle\Tests\DependencyInjection\Fixtures\TestKernel;
use Doctrine\Bundle\DoctrineBundle\Tests\TestCase;
use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\Deprecations\PHPUnit\VerifyDeprecations;
use Doctrine\ORM\Cache\Region;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function get_class;
use function interface_exists;

class CacheCompatibilityPassTest extends TestCase
{
    use VerifyDeprecations;

    public static function setUpBeforeClass(): void
    {
        if (interface_exists(EntityManagerInterface::class)) {
            return;
        }

        self::markTestSkipped('This test requires ORM');
    }

    public function testCacheConfigUsingServiceDefinedByApplication(): void
    {
        $customRegionClass = get_class($this->createMock(Region::class));

        (new class ($customRegionClass) extends TestKernel {
            public function __construct(private readonly string $regionClass)
            {
                parent::__construct(false);
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);
                $loader->load(function (ContainerBuilder $containerBuilder): void {
                    $containerBuilder->loadFromExtension('framework', [
                        'cache' => [
                            'pools' => [
                                'doctrine.system_cache_pool' => ['adapter' => 'cache.system'],
                            ],
                        ],
                    ]);
                    $containerBuilder->loadFromExtension(
                        'doctrine',
                        [
                            'orm' => [
                                'controller_resolver' => ['auto_mapping' => false],
                                'query_cache_driver' => ['type' => 'service', 'id' => 'custom_cache_service'],
                                'result_cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.system_cache_pool'],
                                'second_level_cache' => [
                                    'enabled' => true,
                                    'regions' => [
                                        'filelock' => ['type' => 'filelock', 'lifetime' => 0, 'cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.system_cache_pool']],
                                        'lifelong' => ['lifetime' => 0, 'cache_driver' => ['type' => 'pool', 'pool' => 'doctrine.system_cache_pool']],
                                        'entity_cache_region' => ['type' => 'service', 'service' => $this->regionClass],
                                    ],
                                ],
                            ],
                        ],
                    );
                    $containerBuilder->register($this->regionClass, $this->regionClass);
                    $containerBuilder->setDefinition(
                        'custom_cache_service',
                        new Definition(ArrayAdapter::class),
                    );
                });
            }
        })->boot();

        $this->addToAssertionCount(1);
    }

    #[DoesNotPerformAssertions]
    public function testMetadataCacheConfigUsingPsr6ServiceDefinedByApplication(): void
    {
        (new class (false) extends TestKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);
                $loader->load(static function (ContainerBuilder $containerBuilder): void {
                    $containerBuilder->loadFromExtension(
                        'doctrine',
                        [
                            'orm' => [
                                'controller_resolver' => ['auto_mapping' => false],
                                'metadata_cache_driver' => ['type' => 'service', 'id' => 'custom_cache_service'],
                            ],
                        ],
                    );
                    $containerBuilder->setDefinition(
                        'custom_cache_service',
                        new Definition(ArrayAdapter::class),
                    );
                });
            }
        })->boot();
    }

    #[IgnoreDeprecations]
    public function testMetadataCacheConfigUsingNonPsr6ServiceDefinedByApplication(): void
    {
        $this->expectDeprecationWithIdentifier('https://github.com/doctrine/DoctrineBundle/pull/1365');
        (new class (false) extends TestKernel {
            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                parent::registerContainerConfiguration($loader);
                $loader->load(static function (ContainerBuilder $containerBuilder): void {
                    $containerBuilder->loadFromExtension(
                        'doctrine',
                        ['orm' => ['metadata_cache_driver' => ['type' => 'service', 'id' => 'custom_cache_service']]],
                    );
                    $containerBuilder->setDefinition(
                        'custom_cache_service',
                        (new Definition(DoctrineProvider::class))
                            ->setArguments([new Definition(ArrayAdapter::class)])
                            ->setFactory([DoctrineProvider::class, 'wrap']),
                    );
                });
            }
        })->boot();
    }
}
