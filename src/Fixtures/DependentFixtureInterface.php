<?php

namespace Facile\MongoDbBundle\Fixtures;

interface DependentFixtureInterface
{
    /**
     * @return string[]
     */
    public function getDependencies(): array;
}