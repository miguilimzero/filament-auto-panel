<?php

namespace Miguilim\FilamentAutoResource;

use Filament\Support\Commands\Concerns\CanReadModelSchemas;
use Miguilim\FilamentAutoResource\Doctrine\CustomMySQLSchemaManager;

class AutoResourceHelper
{
    use CanReadModelSchemas;

    use Traits\HasPageGeneration;
    use Traits\HasFormGeneration;
    use Traits\HasTableGeneration;

    public function introspectTable(string $model)
    {
        $modelInstance = app($model);
        $doctrineConnection = $modelInstance
            ->getConnection()
            ->getDoctrineConnection();

        $table = (new CustomMySQLSchemaManager($doctrineConnection, $doctrineConnection->getDatabasePlatform()))
            ->introspectTable($modelInstance->getTable());

        return $table;
    }
}