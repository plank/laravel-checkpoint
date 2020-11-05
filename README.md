# Laravel Checkpoint 

[![Latest Version on Packagist](https://img.shields.io/packagist/v/plank/laravel-checkpoint.svg?style=flat-square)](https://packagist.org/packages/plank/versionable)
[![Build Status](https://img.shields.io/travis/plank/laravel-checkpoint/master.svg?style=flat-square)](https://travis-ci.org/plank/versionable)
[![Quality Score](https://img.shields.io/scrutinizer/g/plank/laravel-checkpoint.svg?style=flat-square)](https://scrutinizer-ci.com/g/plank/versionable)
[![Total Downloads](https://img.shields.io/packagist/dt/plank/laravel-checkpoint.svg?style=flat-square)](https://packagist.org/packages/plank/versionable)

## Table of Contents
- [Laravel Checkpoint](#laravel-checkpoint)
  - [Table of Contents](#table-of-contents)
  - [Why Use This Package](#why-use-this-package)
  - [Installation](#installation)
  - [Concepts](#concepts)
    - [Checkpoints](#checkpoints)
    - [Revisions](#revisions)
  - [Usage](#usage)
    - [Revisioning Models](#revisioning-models)
      - [What gets Revisioned?](#what-gets-revisioned)
      - [Start Revisioning Command](#start-revisioning-command)
    - [Query Scopes](#query-scopes)
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
### Checkpoints
A ```Checkpoint``` is a point in time which is of interest. A ```Checkpoint``` allows you to filter the ```Revision```s 
of your models based on the ```Checkpoint```'s ```checkpoint_date```.

Table: ```checkpoints```

| Field             | Type           | Required  |  Default        |
|-------------------|----------------|:---------:|-----------------|
| id                | bigIncrements  | ✗         | Increment       |
| title             | string         | ✓         |                 | 
| checkpoint_date   | timestamp      | ✓         |                 |
| created_at        | timestamp      | ✗         |                 |
| updated_at        | timestamp      | ✗         |                 |

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
using its ```checkpoint_date``` field, or a string representation of a date compatible with ```Carbon::parse```, or a 
```Carbon``` instance.

#### since($moment)
```php
/**
 * @param $moment Checkpoint|Carbon|string
 */
since($moment = null)
```
This query scope will limit the query to return the *Model* whose ```Revision``` has the max primary key, where
the ```Revision``` was created after the given moment. 

The moment can either be an instance of a ```Checkpoint``` using its ```checkpoint_date``` field, or a string
representation of a date compatible with ```Carbon::parse```, or a ```Carbon``` instance.

#### temporal($upper, $lower)
```php
/**
 * @param $upper Checkpoint|Carbon|string
 * @param $upper Checkpoint|Carbon|string
 */
temporal($upper = null, $lower = null)
```
This query scope will limit the query to return the *Model* whose ```Revision``` has the max primary key created at 
or before ```$upper```. This method can also limit the query to the *Model* whose revision has the max primary key
created after ```$lower```. 

Each argument operates independently of each other and ```$upper``` and ```$lower``` can 
either be an instance of a ```Checkpoint``` using its ```checkpoint_date``` field, or a string representation of a date
compatible with ```Carbon::parse```, or a ```Carbon``` instance.

#### withoutRevisions()
```php
withoutRevisions()
```

This query scope is used to query the models without taking revisioning into consideration.

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

### Unwatched Fields
When updating the fields of a *Model*, some fields may not warrant creating a new ```Revision``` of the *Model*. You can
prevent a new ```Revision``` from being created when specific fields are updated by setting the 
```protected $unwatched``` array on the model being revisioned. 

### Should Revision
If you have more complex cases where you may not want to create a new ```Revision``` when updating a *Model*, you can 
override the ```public function shouldRevision()``` on the *Model* being revisioned. When this method returns a truthy 
value, a new ```Revision``` will be created when updating, and when it returns a falsy value it will not.
    
### Excluded Columns
When creating a new ```Revision``` of a *Model* there may be some fields which do not make sense to have their values 
copied over. In those cases you can add those values to the ``` protected $excludedColumns``` array on the *Model* you
are revisioning.

### Excluded Relations
When creating a new ```Revision``` of a *Model* there may be relations which do not make sense to duplicate. In those 
cases you can add the names of the relations to the``` protected $excludedRelations``` array on the *Model* you are
revisioning. Excluding all relations to the ```Checkpoint```s and other related ```Revision```s are handled by the 
package.  

### Testing

``` bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email massimo@plankdesign.com instead of using the issue tracker.

## Credits

- [Massimo Triassi](https://github.com/m-triassi)
- [Andrew Hanichkovsky](https://github.com/WindOfRussia)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

