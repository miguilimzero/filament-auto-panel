<?php

namespace Miguilim\FilamentAutoPanel\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Types\Type;

use Doctrine\DBAL\Schema\MySQLSchemaManager;

class CustomMySQLSchemaManager extends MySQLSchemaManager
{
      /**
     * {@inheritDoc}
     */
    protected function _getPortableTableColumnDefinition($tableColumn)
    {
        $tableColumn = array_change_key_case($tableColumn, CASE_LOWER);

        $dbType = strtolower($tableColumn['type']);
        $dbType = strtok($dbType, '(), ');
        assert(is_string($dbType));

        $length = $tableColumn['length'] ?? strtok('(), ');

        $fixed = null;

        if (! isset($tableColumn['name'])) {
            $tableColumn['name'] = '';
        }

        $scale = null;
        $precision = null;

        // Modification to differentiate between tinyint(1) and tinyint(4)
        // Between laravel migration ->boolean() and ->tinyInteger() methods
        if($dbType === 'tinyint') {
            $type = ($length === '1') ? 'boolean' : 'smallint';
        } else {
            $type = $this->_platform->getDoctrineTypeMapping($dbType);
        }

        // In cases where not connected to a database DESCRIBE $table does not return 'Comment'
        if (isset($tableColumn['comment'])) {
            $type = $this->extractDoctrineTypeFromComment($tableColumn['comment'], $type);
            $tableColumn['comment'] = $this->removeDoctrineTypeFromComment($tableColumn['comment'], $type);
        }

        switch ($dbType) {
            case 'char':
            case 'binary':
                $fixed = true;
                break;

            case 'float':
            case 'double':
            case 'real':
            case 'numeric':
            case 'decimal':
                if (
                    preg_match(
                        '([A-Za-z]+\(([0-9]+),([0-9]+)\))',
                        $tableColumn['type'],
                        $match,
                    ) === 1
                ) {
                    $precision = $match[1];
                    $scale = $match[2];
                    $length = null;
                }

                break;

            case 'tinytext':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_TINYTEXT;
                break;

            case 'text':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_TEXT;
                break;

            case 'mediumtext':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMTEXT;
                break;

            case 'tinyblob':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_TINYBLOB;
                break;

            case 'blob':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_BLOB;
                break;

            case 'mediumblob':
                $length = AbstractMySQLPlatform::LENGTH_LIMIT_MEDIUMBLOB;
                break;

            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'integer':
            case 'bigint':
            case 'year':
                $length = null;
                break;
        }

        // if ($this->_platform instanceof MariaDb1027Platform) {
        //     $columnDefault = $this->getMariaDb1027ColumnDefault($this->_platform, $tableColumn['default']);
        // } else {
            $columnDefault = $tableColumn['default'];
        // }

        $options = [
            'length' => $length !== null ? (int) $length : null,
            'unsigned' => strpos($tableColumn['type'], 'unsigned') !== false,
            'fixed' => (bool) $fixed,
            'default' => $columnDefault,
            'notnull' => $tableColumn['null'] !== 'YES',
            'scale' => null,
            'precision' => null,
            'autoincrement' => strpos($tableColumn['extra'], 'auto_increment') !== false,
            'comment' => isset($tableColumn['comment']) && $tableColumn['comment'] !== ''
                ? $tableColumn['comment']
                : null,
        ];

        if ($scale !== null && $precision !== null) {
            $options['scale'] = (int) $scale;
            $options['precision'] = (int) $precision;
        }

        $column = new Column($tableColumn['field'], Type::getType($type), $options);

        if (isset($tableColumn['characterset'])) {
            $column->setPlatformOption('charset', $tableColumn['characterset']);
        }

        if (isset($tableColumn['collation'])) {
            $column->setPlatformOption('collation', $tableColumn['collation']);
        }

        return $column;
    }
}