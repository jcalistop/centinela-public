<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $ano
 * @property bool $default
 */
class Year extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    // Define método estático para llamar al año por defecto
    public static function getDefaultYear()
    {
        return self::where('default', 1)->firstorFail();
    }

    // Define el método para dejar un solo default cuando se cree o se actualice un registro
    public function setDefault()
    {
        // Si el campo default del registro actual es true
        if ($this->default) {
            // Actualiza los demás registros para que tengan el campo default en false
            self::where('id', '!=', $this->id)->update(['default' => false]);
        }
    }

    // Define los eventos que disparan el método
    protected static function boot()
    {
        parent::boot();

        // Indica que quieres ejecutar el método setDefault después de que se cree o se actualice un registro
        static::created(function ($year) {
            $year->setDefault();
            Cache::forget('default_year');
        });

        static::updated(function ($year) {
            $year->setDefault();
            Cache::forget('default_year');
        });
    }
}
