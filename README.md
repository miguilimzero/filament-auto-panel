![Filament Breezy cover art](./art/banner.png)

# Filament Auto

[![Latest Version on Packagist](https://img.shields.io/packagist/v/miguilim/filament-auto.svg?style=flat-square)](https://packagist.org/packages/miguilim/filament-auto)
[![Total Downloads](https://img.shields.io/packagist/dt/miguilim/filament-auto.svg?style=flat-square)](https://packagist.org/packages/miguilim/filament-auto)

A plugin to construct your Filament Admin Panel resources, forms and views at execution time like magic. 
This package provide custom Resources and Relation Managers classes that mounts it table, create, view and edit pages at execution time by scanning the database table schema.

> This package is intended for Admin Panels as a database navigator. If you feels you need a more customized resource, re-consider to not use this package.

## Contents

- [Installation](#installation)
- [Usage](#usage)
- [Auto Resource](#auto-resource)
- [Auto Relation Manager](#auto-relation-manager)
- [Auto Action](#auto-action)
- [Enum Dictionary](#enum-dictionary)
- [Visible Columns](#visible-columns)
- [Searchable Columns](#searchable-columns)
- [Overwrite Columns](#overwrite-columns)
- [Widgets](#widgets)
- [Extra Pages](#extra-pages)
- [License](#license)

## Installation

You can install the package via composer:

```sh
composer require miguilim/filament-auto
```

## Usage

Before getting started with the package, you must know some behaviors that the table schema reader have. 
The Filament Auto will take into consideration your actual table schema in the database and not your migration file.
However, it is extremely important to use the correct fields in the migration in order to generate the correct columns.

An example of that is when you use the `boolean()` column method, Laravel will generate a `tinyint(1)` table column. This
specific column type and length will be used by Filament Auto to detect its a boolean column. If you use the `tinyInteger()`
method, it will generate a `tinyint(4)` table column, and therefore will be identified as a numeric column, even if in its context 
it is being used as a boolean.

#### Soft Deletes

The package will detect if the table has soft deletes or not by checking if it has the `SoftDeletes` trait, 
by checking if `bootSoftDeletes` method exists, in the Model. If soft deletes is detected, the will appended the `TrashedFilter` to the filters.

#### Primary Key

Filament Auto will try to detect the primary key by using the `getKeyName()` from the resource or relation manager Model. The primary key
will be searchable by default. It will also be copyable in table and infolist.

#### Default Actions

By default, Auto Resource will append the following default actions:

- Bulk Actions: `DeleteBulkAction or RestoreBulkAction, ForceDeleteBulkAction`
- Table Actions: `ViewAction or RestoreAction`
- Page Actions: `DeleteAction or RestoreAction, ForceDeleteAction`

#### Default Pages

By default, the Auto Resource will have a list and view pages. The create and edit record is available as a modal action in the list and view pages respectively.

#### Default Sorting

By default, the Auto Resource and Auto Relation Manager will try to set the table default sort for the following columns, 
in priority order respectively: `primary key (only if incremented)`, `created_at`, `updated_at`.

## Auto Resource

You can get started by creating your first Auto Resource using the following command:

```sh
php artisan make:filament-auto-resource
```

This command will create the Auto Resource class for you, just as the default filament command. However, it will use the `AutoResource` class instead.
You don't need to list anything now, **you can just access the resource page and see the magic!**

## Auto Relation Manager

Auto Relation Manager construct a table containing the all relationship model columns, excluding the related id or morph.
You can generate your Auto Relation Manager using the following command:

```sh
php artisan make:filament-auto-relation-manager
```

This command will create the Auto Relation Manager for you and you must list it in the `getRelations()` method of your resource.
However, sometimes you may want something more handier. You can create a relation manager inside your resource using the `RelationManagerMounter`.
See the following example of how it works:

```php
use Miguilim\FilamentAuto\Mounters\RelationManagerMounter;

public static function getRelations(): array
{
    return [
        RelationManagerMounter::make(
            resource: static::class,
            relation: 'userBans',
            recordTitleAttribute: 'Bans',
            visibleColumns: ['reason', 'created_at'],
        ),
    ];
}
```

## Auto Action

The Auto Resource and Auto Relation Manager will provider a `getActions()` method, however you cannot use the default Filament action. You must use the `AutoAction` class.
This action type have same methods as Filament Actions, however it provide new methods to set where the action will be shown. This is needed since there is only this array
for all resource actions.

The resource `action` always receive a collection of models and it can be used in the following way:

```php
use Miguilim\FilamentAuto\AutoAction;
use Illuminate\Database\Eloquent\Collection;

public static function getActions(): array
{
    return [
        AutoAction::make('refund')
            ->label('Refund')
            ->icon('heroicon-o-arrow-path')
            ->color('danger')
            ->action(fn (Collection $records) => $records->each->refund())
            ->showOnTable()
            ->showOnViewPage(),
    ];
}
``` 

By default the auto action will not be shown anywhere, so you must set one of the following methods: `showOnTable()`, `showOnBulkAction()` or `showOnViewPage()`.

## Enum Dictionary

Enum dictionary is a feature available to help you formatting a value for your resource. This feature will set a `Badge` in table and infolist, 
and a `Select` in the form with the values you set. You can use it in the following way:

```php
protected static array $enumDictionary = [
    'type' => [
        0 => 'Default',
        1 => 'Administrator',
    ]
];
```

You may customize the badge colors using the following syntax:

```php
protected static array $enumDictionary = [
    'type' => [
        0 => ['Default', 'blue'],
        1 => ['Administrator', 'red'],
    ]
];
```

## Visible Columns

By default all columns will be shown in the Auto Resource or Auto Relation Manager listing. You can customize the visible table columns using:

```php
protected static array $visibleColumns = [
    'name', 'email', 'created_at'
];
```

This feature only sets the column default visibility in the top-right menu of your table. You can enable/disable any column at any time using the panel.

> The primary key column will always be shown. You cannot customize form or infolist columns.

## Searchable Columns

You can set searchable columns for your Auto Resource or Auto Relation Manager using:

```php
protected static array $searchableColumns = [
    'name'  => 'global',
    'email' => 'global',
];
```

You have the following searchable options to use: `global`, `individual` or `both`.

## Overwrite Columns

Sometimes you may want to customize the resource columns entirely, you can overwrite the table column, form field or infolist entry with the `getColumnsOverwrite` method:

```php
use Filament\Tables\Columns\ImageColumn;

public static function getColumnsOverwrite(): array
{
    return [
        'table' => [
            ImageColumn::make('profile_photo_url')
                ->label('Profile Photo')
        ],
        'form' => [
            //
        ],
        'infolist' => [
            //
        ],
    ];
}
```

> You cannot append new columns using this method, only overwrite detected columns. The `make()` parameter name must be the same as the column name in the database.

## Widgets

You can set widgets to the `list` and `view` pages for your Auto Resource independently in the following way:

```php
public static function getHeaderWidgets(): array
{
    return [
        'list' => [
            MyCoolStatsWidget::class,
        ],
        'view' => [
            //
        ],
    ];
}

public static function getFooterWidgets(): array
{
    return [
        'list' => [
            //
        ],
        'view' => [
            //
        ],
    ];
}
```

## Extra Pages

You can append extra pages to your Auto Resource using the `getExtraPages` method:

```php
public static function getExtraPages(): array
{
    return [
        MyCustomResourcePage::route('/custom-path'),
    ];
}
```

## License

Filament Auto is open-sourced software licensed under the [MIT license](LICENSE).