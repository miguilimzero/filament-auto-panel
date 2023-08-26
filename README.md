# Filament Auto

A plugin to construct your Filament Admin Panel resources, forms and views at execution time like magic. 
This package provide custom Resources and Relation Managers classes that mounts it table, create, view and edit pages at execution time by scanning the table structure.

## Contents

- [Installation](#installation)
- [Usage](#usage)
- [Auto Resource](#auto-resource)
- [Auto Relation Manager](#auto-relation-manager)
- [Auto Action](#auto-action)
- [Enum Dictionary](#enum-dictionary)
- [Visible Columns](#visible-columns)
- [Searchable Columns](#searchable-columns)
- [Overwrite Table Columns](#overwrite-table-columns)
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

### Soft Deletes

The package will detect if the table has soft deletes or not by checking if it has the `SoftDeletes` trait in the Model.

### Primary Key

Filament Auto will try to detect the primary key by getting the key name from the resource or relation manager Model.

### Default Filters

By default, the `Auto Resource` and `Auto Relation Manager` will append the `TrashedFilter` if it has soft deletes.

### Default Actions

By default, Auto Resource will append the following default actions:

- Bulk Actions: `DeleteBulkAction or RestoreBulkAction, ForceDeleteBulkAction`
- Table Actions: `ViewAction or RestoreAction`
- Page Actions: `DeleteAction or RestoreAction, ForceDeleteAction`

### Default Pages

By default, the `Auto Resource` will have a list and view pages. The create and edit record is available as a modal action in the view page.

### Default Sorting

By default, the `Auto Resource` and `Auto Relation Manager` will try to set the table default sort for the following columns, 
in priority order: `primary key (only if incremented)`, `created_at`, `updated_at`.

## Auto Resource


## Auto Relation Manager


## Auto Action


## Enum Dictionary


## Visible Columns


## Searchable Columns


## Overwrite Table Columns


## Extra Pages


## License

Filament Auto is open-sourced software licensed under the [MIT license](LICENSE).