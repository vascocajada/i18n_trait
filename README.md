# i18n Trait

i18N Trait is designed to help you handle a multi-language database in PHP using [Eloquent](https://laravel.com/docs/8.x/eloquent#introduction) Models together with the [Symfony](https://symfony.com/) framework.

This trait makes it easier for you to save, update and fetch data from your database, as you can use Eloquent's methods and let the trait handle which translation it should update.

## Installation


Clone the repository into your project and place it next to your other traits.

```bash
git clone https://github.com/vascocajada/i18n_trait.git
```

## Usage

- Use the trait in the Models you translated.
- Set the $translation_foreign_key and $translated_attributes properties in your model.
```php
<?php
// Model/Product.php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TranslatableTrait;

class Product extends Model
{
    use TranslatableTrait;

    private $translation_foreign_key = 'product_id';
    private $translated_attributes = [
        'title',
        'description'
    ];

}
```

- Create a translation model with the name of your model appended by **translation** in pascal case (ex.: ProductTranslation).
- Create the translation table with the name of your model appended by **_translations** and the $translated_attributes as columns.


```php
<?php
// Model/ProductTranslation.php    

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ProductTranslation extends Model
{   
    public $timestamps = false;
}

```


- Set the default locale of your request (check symfony [docs](https://symfony.com/doc/current/translation.html)).
- Use your model as you would usually do with an Eloquent model, only now you will be getting / setting the values of the translation where applies.

```php
<?php

    $product = new \Model\Product();
    $translated_description = $product->description;

    $product->title = 'translated title';
    $product->save(); // saves the model of the translation that matches the default locale


    /** Get all of the models's translations. **/
    $translations = $product->translations();

    /** Translate the model to the given or current language. If doesn't exist in language provided fallback to original model. **
     * $locale - language we are going to translate to - string
     */
    $locale = 'de'; // German country code
    $product->translate($locale);

?>
```
