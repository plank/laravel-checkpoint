# Laravel Checkpoint 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/plank/laravel-checkpoint.svg?style=flat-square)](https://packagist.org/packages/plank/laravel-checkpoint)
[![GitHub Tests Action Status](https://github.com/plank/laravel-checkpoint/actions/workflows/tests.yml/badge.svg)](https://github.com/plank/laravel-checkpoint/actions?query=workflow%3Atests+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/plank/laravel-checkpoint.svg?style=flat-square)](https://packagist.org/packages/plank/laravel-checkpoint)

## Table of Contents
- [Laravel Checkpoint](#laravel-checkpoint)
  - [Table of Contents](#table-of-contents)
  - [Why Use This Package](#why-use-this-package)
  - [Installation](#installation)
  - [Concepts](#concepts)
    - [Timelines](#timelines)
    - [Checkpoints](#checkpoints)
    - [Revisions](#revisions)
  - [Usage](#usage)
    - [Revisioning Models](#revisioning-models)
      - [What gets Revisioned?](#what-gets-revisioned)
      - [Start Revisioning Command](#start-revisioning-command)
    - [Query Scopes](#query-scopes)
      - [Active Checkpoint](#active-checkpoint)
      - [at($moment)](#atmoment)
      - [since($moment)](#sincemoment)
      - [temporal($upper, $lower)](#temporalupper-lower)
      - [withoutRevisions()](#withoutrevisions)
    - [Revision Metadata & Uniqueness](#revision-metadata--uniqueness)
    - [Unwatched Fields](#unwatched-fields)
    - [Should Revision](#should-revision)
    - [Excluded Columns](#excluded-columns)
    - [Excluded Relations](#excluded-relations)
  - [Testing](#testing)
  - [Changelog](#changelog)
  - [Contributing](#contributing)
  - [Security](#security)
  - [Credits](#credits)
  - [License](#license)

## Why Use This Package
Do you need to store the state of how your models change over time? Do you need a way to query and view the state of your models at different points in time? If the answer is yes, then this package is for you! 

## Installation

You can install the package via composer:

```bash
composer require plank/laravel-checkpoint
```

## Concepts
### Timelines
A ```Timeline``` is a way to have completely separate views of your content. A ```Timeline``` allows you to filter the ```Revision```s of your models based on the ```Timeline``` it belongs to.

Table: ```timelines```

| Field             | Type                | Required  |  Default        |
|-------------------|---------------------|:---------:|-----------------|
| id                | bigIncrements       | ✗         | Increment       |
| timeline_id       | unsignedBigInteger  | ✗         |                 |
| title             | string              | ✓         |                 | 
| checkpoint_date   | timestamp           | ✓         |                 |
| created_at        | timestamp           | ✗         |                 |
| updated_at        | timestamp           | ✗         |                 |

### Checkpoints
A ```Checkpoint``` is a point in time which is of interest. A ```Checkpoint``` allows you to filter the ```Revision```s 
of your models based on the ```Checkpoint```'s ```checkpoint_date```.

Table: ```checkpoints```

| Field             | Type                | Required  |  Default        |
|-------------------|---------------------|:---------:|-----------------|
| id                | bigIncrements       | ✗         | Increment       |
| timeline_id       | unsignedBigInteger  | ✗         |                 |
| title             | string              | ✓         |                 | 
| checkpoint_date   | timestamp           | ✓         |                 |
| created_at        | timestamp           | ✗         |                 |
| updated_at        | timestamp           | ✗         |                 |

### Revisions
A ```Revision``` references a record of a ```Model``` in a particular state at a particular point in time. When this 
package is enabled, and you use the ```HasRevisions``` trait on a *Model*, the concept of an instance of a *Model* in 
Laravel changes. Since we want to store ```Revision```s of a *Model*, and have them searchable in their different 
states, the notion that an *Entity* (instance of a *Model*) is associated with exactly one id, is no longer correct. Each 
```Revision``` of a *Model* has its own unique id in the table, even though it represents the same *Entity*. 

The same entity is linked via the ```original_revisionable_id``` field.

Table: ```revisions```

| Field                     |  Type               | Required  |  Default        |
|---------------------------|---------------------|:---------:|-----------------|
| id                        | bigIncrements       | ✗         | Increment       |
| revisionable_id           | unsignedBigInteger  | ✓         |                 |
| revisionable_type         | string              | ✓         |                 |
| original_revisionable_id  | unsignedBigInteger  | ✓         |                 |
| latest                    | boolean             | ✗         | true            |
| metadata                  | json                | ✗         | null            |
| previous_revision_id      | unsignedBigInteger  | ✗         | null            |
| checkpoint_id             | unsignedBigInteger  | ✗         | null            |
| created_at                | timestamp           | ✗         |                 |
| updated_at                | timestamp           | ✗         |                 |

## Usage
### Revisioning Models
To have a model be revisioned, all you need to do is have it use the ```HasRevisions``` trait.

#### What gets Revisioned?
This package handles revisioning by creating a new row for a *Model* in the database every time it changes state in a 
meaningful way. When a new ```Revision``` is created, the package will also recursively duplicate all *Models* related 
via child relationships, and will create new many-to-many relationships in pivot tables.

#### Start Revisioning Command
If you have an existing project with *Models* already populated in the database, the ```php artisan checkpoint:start``` 
command will begin revisioning all of the *Models* which are using the ```HasRevsions``` trait. 

### Query Scopes
The way this package achieves it's goal is by adding scopes (and one global scope) to query models that have revisions. 

#### Active Checkpoint
By setting the active checkpoint ```Checkpoint::setActive($checkpoint)```, all queries for revisioned models will be
scoped to that ```$checkpoint```. Also, when there is an active checkpoint set, any new revisions that get created will be associated with that ```$checkpoint```.

#### at($moment)
```php
/**
 * @param $moment Checkpoint|Carbon|string
 */
at($moment = null)
```
This is the default global query scope added to all queries on a *Model* with ```Revision```s.

This query scope will limit the query to return the *Model* whose ```Revision``` has the max primary key, where
the ```Revision``` was created at or before the given moment. 

The moment can either be an instance of a ```Checkpoint``` 
using its ```checkpoint_date``` field, a string representation of a date or a ```Carbon``` instance.

#### since($moment)
```php
/**
 * @param $moment Checkpoint|Carbon|string
 */
since($moment = null)
```
This query scope will limit the query to return the *Model* whose ```Revision``` has the max primary key, where
the ```Revision``` was created after the given moment. 

The moment can either be an instance of a ```Checkpoint``` using its ```checkpoint_date``` field, a string
representation of a date or a ```Carbon``` instance.

#### temporal($upper, $lower)
```php
/**
 * @param $upper Checkpoint|Carbon|string
 * @param $upper Checkpoint|Carbon|string
 */
temporal($until = null, $since = null)
```
This query scope will limit the query to return the *Model* whose ```Revision``` has the max primary key created at 
or before ```$until```. This method can also limit the query to the *Model* whose revision has the max primary key
created after ```$since```. 

Each argument operates independently of each other and ```$until``` and ```$since``` can 
either be an instance of a ```Checkpoint``` using its ```checkpoint_date``` field, a string representation of a 
date or a ```Carbon``` instance.

#### withoutRevisions()
```php
withoutRevisions()
```

This query scope is used to query the models without taking revisioning into consideration.

### Dynamic Relationships

Inspired by https://reinink.ca/articles/dynamic-relationships-in-laravel-using-subqueries, this package supplies a few
dynamic relationships as a convenience for navigating through a model's revision history. The following scopes will run
subqueries to get the additional columns and eagerload the corresponding relations, saving you the hassle of caching 
them on each of the tables for your revisionable models. As a fallback when these scopes are not applied, we use get 
mutators to run queries and fetch the same columns, making sure the relations are always available but at the expense 
of running a bit more queries. *NOTE: when applying these scopes, you will have extra columns in your models attributes, 
**any update or insert operations will not work.***

#### withNewestAt($until, $since)
```php
/**
 * @param $until Checkpoint|Carbon|string
 * @param $since Checkpoint|Carbon|string
 */
withNewestAt($until = null, $since = null)
```
This scope will retrieve the id of the newest model given the until / since constraints. Stored in the newest_id
attribute, this allows you to use `->newest()` relation as a quick way to navigate to that model. Defaults to the 
newest model in the revision history.

#### withNewest()
This scope is a shortcut of `withNewestAt` with the default parameters. Uses the same attribute, mutator and relation.

#### withInitial()
This scope will retrieve the id of the initial model from its revision history. Stored in the initial_id attribute, 
this allows you to use `->initial()` relation as a quick way to navigate to that first item in the revision history. 

#### withPrevious()
This scope will retrieve the id of the previous model from its revision history. Stored in the previous_id attribute, 
this allows you to use `->previous()` relation as a quick way to navigate to that previous item in the revision history. 

#### withNext()
This scope will retrieve the id of the next model from its revision history. Stored in the next_id attribute, 
this allows you to use `->next()` relation as a quick way to navigate to that next item in the revision history. 

### Revision Metadata & Uniqueness
As a workaround to some package compatibility issues, this package offers a convenient way to store the values of some
columns as ```metadata``` on the ```revisions``` table. The primary use-case for this feature is to deal with columns or 
indexes which force some sort of uniqueness constraint on the *Model's* table.

For example, imagine a ```Room``` model we wish to revision and it has a ```code``` field which needs to be unique. 
Since multiple instances of the same ```Room``` need to exist as revisions, there would be duplicated ```codes```. By
specifying the ```code``` field in the```protected $revisionMeta;``` of the ```Room``` *Model*, this package will
manage this field by storing it as metadata on the ```Revision```. The package achieve's this by overriding the 
```getAttributeValue($value)``` method on the model, to retrieve the value of ```code``` from the ```Revision```. When
saving a new ```Revision``` of the ```Room``` the ```code``` will automatically be saved on the ```metadata``` field of
the revision and set as null on the ```Room```.

### Ignored Fields
When updating the fields of a *Model*, some fields may not warrant creating a new ```Revision``` of the *Model*. You can
prevent a new ```Revision``` from being created when specific fields are updated by setting the ```protected $ignored```
array on the model being revisioned. 

### Should Revision
If you have more complex cases where you may not want to create a new ```Revision``` when updating a *Model*, you can 
override the ```public function shouldRevision()``` on the *Model* being revisioned. When this method returns a truthy 
value, a new ```Revision``` will be created when updating, and when it returns a falsy value it will not.
    
### Excluded Columns
When creating a new ```Revision``` of a *Model* there may be some fields which do not make sense to have their values 
copied over. In those cases you can add those values to the ``` protected $excluded``` array on the *Model* you
are revisioning. Some operations like deleting / restoring / revisioning children require a full copy and will ignore
this option.

### Excluded Relations
When creating a new ```Revision``` of a *Model* there may be relations which do not make sense to duplicate. In those 
cases you can add the names of the relations to the``` protected $excludedRelations``` array on the *Model* you are
revisioning. Excluding all relations to the ```Checkpoint```s and other related ```Revision```s are handled by the 
package.  

## Testing

``` bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email massimo@plankdesign.com instead of using the issue tracker.

## Credits

- [Massimo Triassi](https://github.com/m-triassi)
- [Andrew Hanichkovsky](https://github.com/WindOfRussia)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

