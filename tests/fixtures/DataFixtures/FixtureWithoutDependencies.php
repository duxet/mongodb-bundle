<?php declare(strict_types=1);

namespace Facile\MongoDbBundle\Tests\fixtures\DataFixtures;

use Facile\MongoDbBundle\Capsule\Database;
use Facile\MongoDbBundle\Fixtures\AbstractContainerAwareFixture;
use Facile\MongoDbBundle\Fixtures\DependentFixtureInterface;
use Facile\MongoDbBundle\Fixtures\MongoFixtureInterface;

class FixtureWithoutDependencies extends AbstractContainerAwareFixture implements MongoFixtureInterface, DependentFixtureInterface
{
    public function loadData()
    {
        $doc = [
            'type' => 'fixture',
            'data' => 'test',
        ];

        /** @var Database $connection */
        $connection = $this->getContainer()->get('mongo.connection.test_db');
        $collection = $connection->selectCollection($this->collection());
        $collection->insertOne($doc);
    }

    public function loadIndexes()
    {
    }

    public function collection(): string
    {
        return 'fixtureWithoutDependenciesCollection';
    }

    public function getDependencies(): array
    {
        return [];
    }
}