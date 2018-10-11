<?php declare(strict_types=1);

namespace Facile\MongoDbBundle\Fixtures;

use Symfony\Component\DependencyInjection\ContainerInterface;

final class MongoFixturesLoader
{
    /** @var null|MongoFixtureInterface[] */
    private $loadedClasses;
    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function loadFromDirectory(string $dir): array
    {
        if (! is_dir($dir)) {
            throw new \InvalidArgumentException(sprintf('"%s" does not exist', $dir));
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        return $this->loadFromIterator($iterator);
    }

    private function loadFromIterator(\Iterator $iterator): array
    {
        $includedFiles = array();
        foreach ($iterator as $file) {
            if ($file->getBasename('.php') === $file->getBasename()) {
                continue;
            }
            $sourceFile = realpath($file->getPathName());
            require_once $sourceFile;
            $includedFiles[] = $sourceFile;
        }

        return $this->createFixtures(
            array_filter(
                get_declared_classes(),
                function (string $className) use ($includedFiles) {
                    return \in_array(
                            (new \ReflectionClass($className))->getFileName(),
                            $includedFiles,
                            true
                        )
                        && is_subclass_of(
                            $className,
                            MongoFixtureInterface::class,
                            true
                        );
                }
            )
        );
    }

    /**
     * @param string[] $classNames
     * @return MongoFixtureInterface[]
     */
    private function createFixtures(array $classNames): array
    {
        return array_map(
            function (string $fixtureClassName) {
                $instance = new $fixtureClassName;

                if (! $instance instanceof MongoFixtureInterface) {
                    throw new \InvalidArgumentException('Something very bad');
                }

                $this->decorateFixture($instance);
                // Ugly
                $this->addInstance($instance);

                return $instance;
            },
            $classNames
        );
    }

    private function decorateFixture(MongoFixtureInterface $instance)
    {
        if ($instance instanceof AbstractContainerAwareFixture) {
            $instance->setContainer($this->container);
        }
    }

    public function addInstance(MongoFixtureInterface $list)
    {
        $listClass = \get_class($list);

        if (! isset($this->loadedClasses[$listClass])) {
            $this->loadedClasses[$listClass] = $list;
        }
    }

    public function loadFromFile(string $fileName): array
    {
        if (! is_readable($fileName)) {
            throw new \InvalidArgumentException(sprintf('"%s" does not exist or is not readable', $fileName));
        }

        $iterator = new \ArrayIterator([new \SplFileInfo($fileName)]);

        return $this->loadFromIterator($iterator);
    }

    /**
     * @return null|MongoFixtureInterface[]
     */
    public function getLoadedClasses()
    {
        return $this->loadedClasses;
    }
}
