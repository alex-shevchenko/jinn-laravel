# What is Jinn

Jinn is a code generator which speeds up your work by generating Models, Migrations and APIs 
from a simple YAML based definitions. 
What makes Jinn different from another generators is that it lets you customize the generated code 
without losing the ability to update it when definition changes.

**Read full documentation at [jinncode.dev](https://jinncode.dev/).**

# Key Concepts
Of course, there are plenty of frameworks and libraries which serve the same purposes using various approaches.
This section describes key design decisions which make Jinn different.

## Code generation
Unlike many API and admin panel frameworks, Jinn uses code generation as it gives following benefits:

* Only the code which is needed is generated. I.e. if you don't need an `update` method in your 
API, it will not be generated at all.
* The generated code does not have to be very generic, which makes it much simpler.

Symfony and Laravel frameworks also use code generation. However, they apply caching approach, i.e. generate the code at runtime.
Jinn generates the code at build-time, which means that there are no first-run performance drawbacks and let's you to inspect the code easily.

All the above means that:

* Jinn carries no performance drawbacks comparing to the other approaches.
* It is easy to inspect the code, understand how it works, extend and customize it.

## Base classes
Each class generated by Jinn (except of Migrations) is split into two files: 

1. An empty class under your sources folder which extends a
1. Base class, located in a separate folder which contains all the logic

The idea is borrowed from `Propel`, a well-known in the past PHP ORM. Main class is 
generated only once and never updated, so you can use it to customize generated logic freely.
At the same Jinn can update the base class logic for you whenever definition changes.

## Database
Currently Jinn supports SQL databases only. MongoDB support is planned.

Jinn is designed to manage it's database tables and models. I.e. it is not able to 
work with existing models and database tables. It also expects that no changes will be 
made to it's database tables, otherwise they may be overwritten. At the same time,
it is possible to have Jinn-managed and non-Jinn-managed tables in the same database.

## Frameworks
Jinn reference implementation is made for Laravel, but it is designed 
to allow implementation for any framework. Contributors are welcome. 

Further down this guide the sections which are specific to Laravel will be marked correspondingly.

# Installation
## Setup via composer
    composer require jinn/jinn-laravel@dev-master
## Publish Jinn config file
    php artisan vendor:publish --provider="Jinn\Laravel\JinnServiceProvider"
## Create Jinn folder structure
Default structure:  
```
jinn 
- def
- gen 
```
Alternative structure can be configured via `config/jinn.php`.
Further guide will use default configuration. Changing Jinn configuration 
should result in corresponding changes to the next steps.

## Configure autoload
Edit `composer.json`, locate autoload section and add a line as follows:
```json
"autoload": {
    "psr-4": {
        ...
        "JinnGenerated\\": "jinn/gen/" 
    }
}
```
Then ask composer to update autoload files:

```shell script
composer dump-autoload
```

# Getting Started
## Basic Definition
Copy the following definition into `jinn/def/entities.yaml` or create your own.
```yaml
User:
  class:
    extends: Illuminate\Foundation\Auth\User
  properties:
    name: string
    email: { type: string, unique: true }
    password: string
    avatar_filename: { type: string, required: false }

Post:
  properties:
    content: text
    author: { entity: User, relation: many-to-one }
    published_at: datetime
    comments: { entity: Comment, relation: one-to-many }

Comment:
  properties:
    content: text
    author: { entity: User, relation: many-to-one }
    published_at: datetime
``` 
## Generation (Laravel)
Ask Jinn to generate the files
```shell script
php artisan jinn
```

Inspect generated files under `app` and `jinn/gen` folders.

# TODO
- [X] Models:
    - [ ] More field types?
    - [ ] Different types for `id`
- [X] APIs:
    - [ ] Related controller
    - [ ] Additional methods: associated/disassociate, other?
    - [ ] Specs
- [X] Migrations:
    - [ ] MongoDB
- [ ] Admin panel
- [ ] File watcher
- [ ] Symfony implementation
