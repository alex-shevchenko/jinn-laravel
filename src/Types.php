<?php


namespace Jinn\Laravel;


class Types extends \Jinn\Models\Types
{
    public static function toEloquentType(string $type) {
        switch ($type) {
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
            default:
                return null;
        }
    }
}
