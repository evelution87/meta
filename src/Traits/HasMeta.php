<?php

namespace Evelution\Meta\Traits;

use Evelution\Meta\Models\Meta;
use Evelution\Meta\Interfaces\CachableAttributes;
use Illuminate\Support\Collection as BaseCollection;

trait HasMeta {

    use CachesAttributes;

    public function scopeHasMeta( $query, $key, $alias = 'meta' ) {

        return $query->select( $query->getQuery()->from . '.*', $alias . '.key', $alias . '.value' )
                     ->leftJoin( 'meta as ' . $alias, $query->getQuery()->from . '.id', '=', $alias . '.meta_id' )
                     ->whereRaw( '`' . $alias . '`.`meta_type` = \'' . addslashes( __CLASS__ ) . '\'' )
                     ->whereRaw( '`' . $alias . '`.`key` = \'' . $key . '\'' );
    }

    public function scopeWhereMeta( $query, $key, $operator = '=', $value = null, $alias = 'meta' ) {

        if ( is_null( $value ) ) {
            $value    = $operator;
            $operator = '=';
        }

        if ( is_array( $value ) ) {
            return $this->scopeHasMeta( $query, $key, $alias )->where( function( $query ) use ( $operator, $value, $alias ) {
                foreach ( $value as $i => $sub_value ) {

                    $query_value = '"' . $sub_value . '"';
                    if ( strtolower( $operator ) === 'like' ) {
                        $query_value = '%' . $query_value . '%';
                    }
                    if ( in_array( $operator, [ '>', '<', '>=', '<=' ] ) ) {
                        if ( !$i ) {
                            $query->whereRaw( 'TRIM(\'"\' FROM `' . $alias . '`.`value`) ' . $operator . ' ' . trim( $query_value, '"' ) );
                        } else {
                            $query->orWhereRaw( 'TRIM(\'"\' FROM `' . $alias . '`.`value`) ' . $operator . ' \'' . $query_value . '\'' );
                        }
                    } else {
                        if ( !$i ) {
                            $query->whereRaw( '`' . $alias . '`.`value` ' . $operator . ' \'' . $query_value . '\'' );
                        } else {
                            $query->orWhereRaw( '`' . $alias . '`.`value` ' . $operator . ' \'' . $query_value . '\'' );
                        }
                    }

                }
            } );
        }

        $query_value = '"' . $value . '"';
        if ( strtolower( $operator ) === 'like' ) {
            $query_value = '%' . $query_value . '%';
        }

        if ( in_array( $operator, [ '>', '<', '>=', '<=' ] ) ) {
            return $this->scopeHasMeta( $query, $key, $alias )
                        ->whereRaw( 'TRIM(\'"\' FROM `' . $alias . '`.`value`) ' . $operator . ' ' . trim( $query_value, '"' ) );
        } else {
            return $this->scopeHasMeta( $query, $key, $alias )
                        ->whereRaw( '`' . $alias . '`.`value` ' . $operator . ' \'' . $query_value . '\'' );
        }
    }

    public function meta() {
        return $this->morphMany( Meta::class, 'meta' );
    }

    public function has_meta( $key ) {
        return !is_null( $this->get_meta( $key ) );
    }

    public function get_meta( $key = null, $default = null, $complete_record = false ) {

        if ( !is_null( $key ) ) {

            if ( is_array( $key ) ) {
                if ( $complete_record ) {
                    return $this->meta()->whereIn( 'key', $key )->get();
                }

                return $this->meta()->whereIn( 'key', $key )->get()->pluck( 'value', 'key' );
            }

            if ( strpos( $key, '.' ) ) {
                [ $key, $dot_key ] = explode( '.', $key, 2 );
                $meta = $this->get_meta( $key );

                return data_get( $meta, $dot_key, $default );
            }

            if ( $complete_record ) {
                return $this->meta()->where( 'key', $key )->first();
            }

            return $this->meta()->where( 'key', $key )->first()->value ?? $default;
        }

        if ( $complete_record ) {
            return $this->meta()->get();
        }

        return $this->meta()->get()->pluck( 'value', 'key' );
    }

    public function get_meta_collection( $key = null, $default = null ) {
        return $this->get_meta( $key, $default, true )->each( function( $item ) {
            $item->value = $this->cast_meta( $item->key, $item->value );
        } );
    }

    public function cast_meta( $key, $value ) {
        if ( !empty( $this->casts_meta ?? [] ) && isset( $this->casts_meta[ $key ] ) ) {

            $castType = $this->casts_meta[ $key ];

            switch ( $castType ) {
                case 'int':
                case 'integer':
                    return (int)$value;
                case 'real':
                case 'float':
                case 'double':
                    return $this->fromFloat( $value );
                case 'decimal':
                    return $this->asDecimal( $value, explode( ':', $this->getCasts()[ $key ], 2 )[1] );
                case 'string':
                    return (string)$value;
                case 'bool':
                case 'boolean':
                    return (bool)$value;
                case 'object':
                    return $this->fromJson( $value, true );
                case 'array':
                case 'json':
                    return $this->fromJson( $value );
                case 'collection':
                    return new BaseCollection( $this->fromJson( $value ) );
                case 'date':
                    return $this->asDate( $value );
                case 'datetime':
                case 'custom_datetime':
                    return $this->asDateTime( $value );
                case 'timestamp':
                    return $this->asTimestamp( $value );
            }

        }

        return $value;
    }

    public function touch_meta( $key ) {
        $query = $this->meta();
        if ( is_null( $key ) ) {
            $query->touch();
        } else if ( is_array( $key ) ) {
            $query->whereIn( 'key', $key )->touch();
        } else {
            $query->where( 'key', $key )->touch();
        }
    }

    public function unset_meta( $key ) {
        if ( is_array( $key ) ) {
            return $this->meta()->whereIn( 'key', $key )->delete();
        }

        return $this->meta()->where( 'key', $key )->delete();
    }

    public function update_meta( $key, $dot_key, $value ) {
        $meta = $this->get_meta( $key );
        data_set( $meta, $dot_key, $value );
        $this->set_meta( $key, $meta );
    }

    public function set_meta( $key, $value = null ) {
        if ( is_object( $key ) ) {
            $key = (array)$key;
        }
        if ( is_array( $key ) ) {
            $metas = [];
            foreach ( $key as $k => $value ) {
                if ( is_null( $value ) ) {
                    $this->unset_meta( $k );
                } else {
                    $meta = $this->meta()->where( 'key', $k )->first();
                    if ( is_null( $meta ) ) {
                        // new
                        $meta      = new Meta;
                        $meta->key = $k;
                    }
                    $meta->value = $value;
                    $metas[]     = $meta;
                }
            }
            $this->meta()->saveMany( $metas );
        } else if ( !is_null( $value ) ) {
            $this->unset_meta( $key );
        } else {
            $meta = $this->meta()->where( 'key', $key )->first();
            if ( is_null( $meta ) ) {
                // new
                $meta      = new Meta;
                $meta->key = $key;
            }
            $meta->value = $value;
            $this->meta()->save( $meta );
        }
    }

    public function getMetaAttribute() {

        if ( $this instanceof CachableAttributes ) {
            return $this->remember( 'meta', 0, function() {

                if ( $this->relationLoaded( 'meta' ) ) {
                    $data = $this->getRelation( 'meta' );
                } else {
                    $data = $this->meta()->get();
                }

                return (object)$data->pluck( 'value', 'key' )->toArray();

            } );
        }

        if ( $this->relationLoaded( 'meta' ) ) {
            $data = $this->getRelation( 'meta' );
        } else {
            $data = $this->meta()->get();
        }

        return (object)$data->pluck( 'value', 'key' )->toArray();

    }

}
