<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

use \App\Service\Utils;

trait TranslatableTrait
{
    private $locale_key = 'locale';

    /**
     * Get all of the models's translations.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function translations()
    {
        $model_name = self::class.'Translation';
        return $this->hasMany($model_name, $this->translation_foreign_key);
    }

    /**
     * Translate the model to the given or current language. If doesn't exist in language provided fallback to original model.
     *
     * @param  string|null  $locale
     * @return \Pine\Translatable\Translation|null
     */
    public function translate($locale = null)
    {
        if ($locale === null) {
            $locale = Utils::getLocale();
        }

        if ($locale !== null &&
            $translation = $this->getTranslation($locale)
        ) {
            return $translation;
        } elseif ($translation = $this->getTranslation($this->getDefaultLocale())) {
            return $translation;
        } else {
            return $this;
        }
    }

    /**
     * get default locale
     *
     * @return string|null
     */
    public function getDefaultLocale()
    {
        if(!isset($GLOBALS['request'])){
            return Utils::getLocale(); // Will try to use session set locale
        }
        $request = $GLOBALS['request'];
        return $request->getDefaultLocale();
    }

    /*
     * Translate model for given locale.
     *
     * @param  string|null  $locale
     * @return \Pine\Translatable\Translation|null
     */
    public function getTranslation($locale)
    {
        return $this->translations->firstWhere($this->locale_key, $locale);
    }

    /*
     * Translate model for given locale or Create new translated model if not exists.
     *
     * @param  string|null  $locale
     * @return \Pine\Translatable\Translation|null
     */
    public function getTranslationOrNew($locale = null)
    {
        if (!$locale) { $locale = Utils::getLocale(); }

        if ($translated_model = $this->getTranslation($locale)) {
            return $translated_model;
        }

        return $this->getNewTranslation($locale);
    }

    /*
     * Create translation
     *
     * @param  string|null  $locale
     * @return \Pine\Translatable\Translation|null
     */
    public function getNewTranslation($locale)
    {
        $model_name = self::class . 'Translation';
        $new_translated_model = new $model_name();
        $new_translated_model->setAttribute($this->locale_key, $locale);
        $this->translations->add($new_translated_model);

        return $new_translated_model;
    }

    /*
     * Fetch attribute. If attribute is translatable fetches translated attribute. Overwrites eloquent's get attribute
     *
     * @param  string|null  $key
     * @return \Pine\Translatable\Translation|null
     */
    public function getAttribute($key)
    {
        if (in_array($key ,$this->translated_attributes)) {
            return $this->translate()->getAttributeValue($key);
        }
        return parent::getAttribute($key);
    }

    /*
     * Convert model to translated array. Overwrites eloquent's to array method.
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        foreach ($this->translated_attributes as $key) {
            $attributes[$key] = $this->translate()->$key;
        }

        return $attributes;
    }

    /**
     * Save model and its respective translations. Overwrites eloquent's save method.
     *
     * @param array $options
     * @return bool
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            if ($this->isDirty()) {
                // If $this->exists and dirty, parent::save() has to return true. If not,
                // an error has occurred. Therefore we shouldn't save the translations.
                if (parent::save($options)) {
                    return $this->saveTranslations();
                }
                return false;
            } else {
                // If $this->exists and not dirty, parent::save() skips saving and returns
                // false. So we have to save the translations
                if ($this->fireModelEvent('saving') === false) {
                    return false;
                }
                if ($saved = $this->saveTranslations()) {
                    $this->fireModelEvent('saved', false);
                    $this->fireModelEvent('updated', false);
                }
                return $saved;
            }
        } elseif (parent::save($options)) {
            // We save the translations only if the instance is saved in the database.
            return $this->saveTranslations();
        }
        return false;
    }

    protected function saveTranslations()
    {
        $saved = true;
        foreach ($this->translations as $translation) {
            if ($saved && $this->isTranslationDirty($translation)) {
                if (! empty($connection_name = $this->getConnectionName())) {
                    $translation->setConnection($connection_name);
                }
                $translation->setAttribute($this->getRelationKey(), $this->getKey());
                $saved = $translation->save();
            }
        }
        return $saved;
    }

    /*
     * Sets attribute. If attribute is translatable sets translated attribute.
     *
     * @param string $key
     * @param string $value
     * @return bool
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key ,$this->translated_attributes)) {
            $this->getTranslationOrNew()->$key = $value;
            return $this;
        }
        return parent::setAttribute($key, $value);
    }

    /**
     * Checks if translation is dirty
     *
     * @param \Illuminate\Database\Eloquent\Model $translation
     * @return bool
     */
    protected function isTranslationDirty(Model $translation)
    {
        $dirty_attributes = $translation->getDirty();
        unset($dirty_attributes[$this->locale_key]);
        return count($dirty_attributes) > 0;
    }

    /**
     * Get foreign key used to translate this model
     *
     * @return string
     */
    public function getRelationKey()
    {
        if ($this->translation_foreign_key) {
            $key = $this->translation_foreign_key;
        } elseif ($this->primaryKey !== 'id') {
            $key = $this->primaryKey;
        } else {
            $key = $this->getForeignKey();
        }
        return $key;
    }

    /**
     * Get translations table name of current model
     *
     * @return string
     */
    private function getTranslationsTable()
    {
        $model_name = self::class . 'Translation';
        $new_translated_model = new $model_name();
        return $new_translated_model->getTable();
    }

    /**
     * Scope results to only translated
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $key
     * @param string value
     * @param string locale
     * @return array|null
     */
    public function scopeWhereTranslation(Builder $query, $key, $value, $locale = null)
    {
        $translations_table = $this->getTranslationsTable();
        return $query->whereHas('translations', function (Builder $query) use ($key, $value, $locale, $translations_table) {
            $query->where($translations_table.'.'.$key, $value);
            if ($locale) {
                $query->where($translations_table.'.'.$this->locale_key, $locale);
            }
        });
    }
}
