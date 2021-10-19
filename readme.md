#Setup
##1. Publish Jinn config file
`php artisan vendor:publish --provider="Jinn\Laravel\JinnServiceProvider"`
##2. Create Jinn folder structure
Default structure:  
```
jinn 
- def
- gen 
```
Alternative structure can be configured via `config/jinn.php`.
Further guide will use default configuration. Changing Jinn configuration 
should result in corresponding changes to the next steps.
##3. Configure autoload
Edit `composer.json`, locate autoload section and add a line as follows:
```
"autoload": {
    "psr-4": {
        ...
        "JinnGenerated\\": "jinn/gen/" 
    }
}
```
##5. Create definitions
Create one or more yaml files under `jinn/def` and define your entities.
##6. Ask Jinn to generate the code
`php artisan jinn`
##7. __(Optional)__ Enable Jinn API routing
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
