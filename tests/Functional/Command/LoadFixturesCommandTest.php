<?php

declare(strict_types=1);

namespace Facile\MongoDbBundle\Tests\Functional\Command;

use Facile\MongoDbBundle\Command\LoadFixturesCommand;
use Facile\MongoDbBundle\Tests\Functional\AppTestCase;
use MongoDB\Collection;
use MongoDB\Database;
use Symfony\Component\Console\Tester\CommandTester;

class LoadFixturesCommandTest extends AppTestCase
{
    /** @var Database $conn */
    private $conn;

    protected function setUp()
    {
        parent::setUp();
        $this->conn = $this->getContainer()->get('mongo.connection');
        $this->assertEquals('testFunctionaldb', $this->conn->getDatabaseName());
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->conn->drop();
    }

    public function test_command()
    {
        $this->conn->createCollection('testFixturesCollection');

        $this->getApplication()->add(new LoadFixturesCommand());

        $command = $this->getApplication()->find('mongodb:fixtures:load');

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'command' => $command->getName(),
                'addFixturesPath' => __DIR__ . '/../../fixtures/DataFixtures'
            ]
        );

        /** @var Collection $collection */
        $collection = $this->conn->selectCollection('testFixturesCollection');
        $fixtures = $collection->find(['type' => 'fixture']);
        $fixtures = $fixtures->toArray();

        self::assertCount(1, $fixtures);
        self::assertEquals('fixture', $fixtures[0]['type']);
        self::assertEquals('test', $fixtures[0]['data']);

        self::assertContains('Done, loaded 4 fixtures files', $commandTester->getDisplay());
    }

    public function test_command_not_fixtures_found()
    {
        /** @var Database $conn */
        $conn = $this->getContainer()->get('mongo.connection');
        self::assertEquals('testFunctionaldb', $conn->getDatabaseName());

        $this->getApplication()->add(new LoadFixturesCommand());

        $command = $this->getApplication()->find('mongodb:fixtures:load');

        $commandTester = new CommandTester($command);

        $this->expectException(\InvalidArgumentException::class);
        $commandTester->execute([]);

        $conn->dropCollection('testFixturesCollection');
    }
}