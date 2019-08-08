<?php

namespace Facile\MongoDbBundle\Tests\Functional\DependencyInjection;

use Facile\MongoDbBundle\Capsule\Database as LoggerDatabase;
use Facile\MongoDbBundle\Command\AbstractCommand;
use Facile\MongoDbBundle\DependencyInjection\MongoDbBundleExtension;
use Facile\MongoDbBundle\Event\ConnectionEvent;
use Facile\MongoDbBundle\Event\QueryEvent;
use Facile\MongoDbBundle\Services\Explain\ExplainQueryService;
use Facile\MongoDbBundle\Services\Loggers\MongoQueryLogger;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use MongoDB\Database;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class MongoDbBundleExtensionTest extends AbstractExtensionTestCase
{
    const LOGGER_PUBLIC_ALIAS = 'facile_mongo_db.logger.public';
    const DISPATCHER_PUBLIC_ALIAS = 'facile_mongo_db.event_dispatcher.public';

    protected function setUp()
    {
        parent::setUp();
        $this->setParameter('kernel.environment', 'dev');
        $this->container->setDefinition('debug.stopwatch', new Definition(Stopwatch::class));
        $this->container->setAlias(self::LOGGER_PUBLIC_ALIAS, new Alias('facile_mongo_db.logger', true));
        $this->container->setAlias(self::DISPATCHER_PUBLIC_ALIAS, new Alias('facile_mongo_db.event_dispatcher', true));
    }

    public function test_load()
    {
        $this->load(
            [
                'clients' => [
                    'test_client' => [
                        'uri' => 'mongodb://foo:bar@localhost:8080',
                    ],
                ],
                'connections' => [
                    'test_db' => [
                        'client_name' => 'test_client',
                        'database_name' => 'testdb',
                    ],
                ],
            ]
        );
        $this->compile();
        // Alias connections
        $this->assertContainerBuilderHasService('mongo.connection', Database::class);
        $defaultConnection = $this->container->get('mongo.connection');
        $this->assertInstanceOf(Database::class, $defaultConnection);
        $this->assertInstanceOf(LoggerDatabase::class, $defaultConnection);
        $this->assertSame('testdb', $defaultConnection->getDatabaseName());

        // 'test_db' connection
        $this->assertContainerBuilderHasService('mongo.connection.test_db', Database::class);
        $defaultConnection = $this->container->get('mongo.connection.test_db');

        $this->assertInstanceOf(Database::class, $defaultConnection);
        $this->assertInstanceOf(LoggerDatabase::class, $defaultConnection);
        $this->assertSame('testdb', $defaultConnection->getDatabaseName());

        $this->assertContainerBuilderHasService('facile_mongo_db.logger', MongoQueryLogger::class);
        $logger = $this->container->get(self::LOGGER_PUBLIC_ALIAS);
        $this->assertInstanceOf(MongoQueryLogger::class, $logger);

        $this->assertContainerBuilderHasService('facile_mongo_db.data_collector.listener');

        /** @var EventDispatcherInterface $ed */
        $ed = $this->container->get(self::DISPATCHER_PUBLIC_ALIAS);
        $this->assertInstanceOf(EventDispatcher::class, $ed);
        self::assertCount(1, $ed->getListeners(QueryEvent::QUERY_EXECUTED));
        self::assertCount(1, $ed->getListeners(ConnectionEvent::CLIENT_CREATED));

        $this->assertContainerBuilderHasService('mongo.explain_query_service', ExplainQueryService::class);
    }

    public function test_load_data_collection_disabled()
    {
        $this->load(
            [
                'clients' => [
                    'test_client' => [
                        'uri' => 'mongodb://foo:bar@localhost:8080',
                    ],
                ],
                'connections' => [
                    'test_db' => [
                        'client_name' => 'test_client',
                        'database_name' => 'testdb',
                    ],
                ],
                'data_collection' => false,
            ]
        );
        $this->compile();
        // Alias connections
        $this->assertContainerBuilderHasService('mongo.connection', Database::class);
        $defaultConnection = $this->container->get('mongo.connection');
        $this->assertInstanceOf(Database::class, $defaultConnection);
        $this->assertInstanceOf(LoggerDatabase::class, $defaultConnection);
        $this->assertSame('testdb', $defaultConnection->getDatabaseName());

        // 'test_db' connection
        $this->assertContainerBuilderHasService('mongo.connection.test_db', Database::class);
        $defaultConnection = $this->container->get('mongo.connection.test_db');

        $this->assertInstanceOf(Database::class, $defaultConnection);
        $this->assertInstanceOf(LoggerDatabase::class, $defaultConnection);
        $this->assertSame('testdb', $defaultConnection->getDatabaseName());

        $this->assertContainerBuilderNotHasService('facile_mongo_db.logger');
        $this->assertContainerBuilderNotHasService('facile_mongo_db.data_collector.listener');

        /** @var EventDispatcherInterface $ed */
        $ed = $this->container->get(self::DISPATCHER_PUBLIC_ALIAS);
        self::assertCount(0, $ed->getListeners());
    }

    public function test_load_env_prod()
    {
        $this->setParameter('kernel.environment', 'prod');
        $this->load(
            [
                'clients' => [
                    'test_client' => [
                        'uri' => 'mongodb://foo:bar@localhost:8080',
                    ],
                ],
                'connections' => [
                    'test_db' => [
                        'client_name' => 'test_client',
                        'database_name' => 'testdb',
                    ],
                ],
            ]
        );
        $this->compile();

        // 'test_db' connection
        $this->assertContainerBuilderHasService('mongo.connection.test_db', Database::class);
        $defaultConnection = $this->container->get('mongo.connection.test_db');

        $this->assertContainerBuilderNotHasService('facile_mongo_db.data_collector.listener');

        /** @var EventDispatcherInterface $ed */
        $ed = $this->container->get(self::DISPATCHER_PUBLIC_ALIAS);
        self::assertEmpty($ed->getListeners());

        $this->assertInstanceOf(Database::class, $defaultConnection);
        $this->assertNotInstanceOf(LoggerDatabase::class, $defaultConnection);
        $this->assertSame('testdb', $defaultConnection->getDatabaseName());
    }

    public function test_load_multiple()
    {
        $this->load(
            [
                'clients' => [
                    'test_client' => [
                        'uri' => 'mongodb://foo:bar@localhost:8080',
                    ],
                    'other_client' => [
                        'uri' => 'mongodb://mee:zod@localhost.dev:8081',
                    ],
                ],
                'connections' => [
                    'test_db' => [
                        'client_name' => 'test_client',
                        'database_name' => 'testdb',
                    ],
                    'other_db' => [
                        'client_name' => 'other_client',
                        'database_name' => 'otherdb',
                    ],
                    'test_db_2' => [
                        'client_name' => 'test_client',
                        'database_name' => 'testdb_2',
                    ],
                ],
            ]
        );
        $this->compile();

        // Alias connection
        $this->assertContainerBuilderHasService('mongo.connection', Database::class);
        $defaultConnection = $this->container->get('mongo.connection');
        $this->assertInstanceOf(Database::class, $defaultConnection);
        $this->assertSame('testdb', $defaultConnection->getDatabaseName());

        // 'test_db' connection
        $this->assertContainerBuilderHasService('mongo.connection.test_db', Database::class);
        $defaultConnection = $this->container->get('mongo.connection.test_db');
        $this->assertInstanceOf(Database::class, $defaultConnection);
        $this->assertSame('testdb', $defaultConnection->getDatabaseName());

        // 'other_db' connection
        $this->assertContainerBuilderHasService('mongo.connection.other_db', Database::class);
        $testConnection = $this->container->get('mongo.connection.other_db');
        $this->assertInstanceOf(Database::class, $testConnection);
        $this->assertSame('otherdb', $testConnection->getDatabaseName());

        // 'test_db_2' connection
        $this->assertContainerBuilderHasService('mongo.connection.test_db_2', Database::class);
        $testConnection = $this->container->get('mongo.connection.test_db_2');
        $this->assertInstanceOf(Database::class, $testConnection);
        $this->assertSame('testdb_2', $testConnection->getDatabaseName());
    }

    /**
     * @dataProvider commandNamesProvider
     */
    public function testCommands()
    {
        $command = 'facile_mongo_db.command.drop_database';
        $publicAlias = $command . '.public';
        $this->container->setAlias($publicAlias, new Alias($command, true));

        $this->load(
            [
                'clients' => [
                    'test_client' => [],
                ],
                'connections' => [
                    'test_db' => [
                        'client_name' => 'test_client',
                        'database_name' => 'testdb',
                    ],
                ],
            ]
        );

        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithTag($command, 'console.command');
        $this->assertInstanceOf(AbstractCommand::class, $this->container->get($publicAlias));
    }

    public function commandNamesProvider(): array 
    {
        return [
            ['facile_mongo_db.command.drop_database'],
            ['facile_mongo_db.command.drop_collection'],
            ['facile_mongo_db.command.load_fixtures'],
        ];
    }

    /**
     * Return an array of container extensions you need to be registered for each test (usually just the container
     * extension you are testing.
     *
     * @return ExtensionInterface[]
     */
    protected function getContainerExtensions()
    {
        return [
            new MongoDbBundleExtension(),
        ];
    }
}
