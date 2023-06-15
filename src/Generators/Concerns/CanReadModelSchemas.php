<?php

namespace Miguilim\FilamentAutoResource\Generators\Concerns;

use Miguilim\FilamentAutoResource\Doctrine\CustomMySQLSchemaManager;

use Filament\Support\Commands\Concerns\CanReadModelSchemas as CanReadModelSchemasBase;

trait CanReadModelSchemas
{
    use CanReadModelSchemasBase;

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