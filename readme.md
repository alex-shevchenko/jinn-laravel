#Why Jinn
#Key Concepts
#Getting Started
#Installation
##Setup via composer
    composer require jinn/jinn-laravel@dev-master
##Publish Jinn config file
    php artisan vendor:publish --provider="Jinn\Laravel\JinnServiceProvider"
##Create Jinn folder structure
Default structure:  
```
jinn 
- def
- gen 
```
Alternative structure can be configured via `config/jinn.php`.
Further guide will use default configuration. Changing Jinn configuration 
should result in corresponding changes to the next steps.
##Configure autoload
Edit `composer.json`, locate autoload section and add a line as follows:
```
"autoload": {
    "psr-4": {
        ...
        "JinnGenerated\\": "jinn/gen/" 
    }
}
```
Ask composer to update autoload file:

    composer dump-autoload
##__(Optional)__ Enable Jinn API routing
In case if your definition includes APIs, when first executed 
Jinn will generate API routing file under `jinn/gen/routes/api.php`.
The file must be required from the main `routes/api.php` as follows
```php
$jinn = require(base_path() . '/jinn/gen/routes/api.php');
Route::group($jinn);
```
Here you may add any middleware to the group as follows
```php
Route::middleware(['first', 'second'])->group($jinn);
```
#Basic Usage
##Definitions
Jinn reads all files from it's definitions directory. 
It is expected that all of them valid YAML files.

For small projects it is reasonable to use a single file for all definitions. 
You can name it `entities.yaml`, for example. 

For larger projects there are several strategies available for using multiple files.
It is covered later in this documentation.
##Basic Definition
```yaml

``` 
##Ask Jinn to generate the code
`php artisan jinn`
