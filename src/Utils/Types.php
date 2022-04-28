<?php


namespace Jinn\Laravel\Utils;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Jinn\Definition\Types as BaseTypes;

class Types extends BaseTypes
{
    public static function toEloquentType(string $type) {
        switch ($type) {
            case self::EMAIL:
            case self::STRING:
                return 'string';
            case self::TEXT:
                return 'text';
            case self::INT:
                return 'integer';
            case self::BIGINT:
                return 'unsignedBigInteger';
            case self::FLOAT:
                return 'float';
            case self::BOOL:
                return 'boolean';
            case self::DATE:
                return 'date';
            case self::DATETIME:
                return 'dateTime';
            case self::JSON:
                return 'json';
            default:
                return null;
        }
    }

    public static function toEloquentCast(string $type) {
        switch ($type) {
            case self::EMAIL:
            case self::STRING:
            case self::TEXT:
                return 'string';
            case self::INT:
                return 'integer';
            case self::FLOAT:
                return 'float';
            case self::BOOL:
                return 'boolean';
            case self::DATE:
                return 'date';
            case self::DATETIME:
                return 'datetime';
            case self::JSON:
                return AsArrayObject::class;
            default:
                return null;
        }
    }

    public static function toValidation(string $type) {
        switch ($type) {
            case self::EMAIL:
                return 'email';
            case self::INT:
            case self::BIGINT:
                return 'integer';
            case self::FLOAT:
                return 'numeric';
            case self::DATE:
            case self::DATETIME:
                return 'date';
            case self::STRING:
            case self::TEXT:
            case self::BOOL:
            case self::JSON:
            default:
                return null;
        }
    }
}
