<?php declare(strict_types=1);

namespace Facile\MongoDbBundle\Fixtures;

interface MongoFixtureInterface
{
    public function loadData();

    public function loadIndexes();

    public function collection(): string;
}
