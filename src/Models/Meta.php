<?php

namespace Evelution\Meta\Models;

use Illuminate\Database\Eloquent\Model;

class Meta extends Model {

    protected $casts = [
        'value' => 'object',
    ];

    protected $fillable = [ 'key', 'value' ];
    protected $guarded = [];

    public function getTable() {
        return 'meta';
    }

    public function meta() {
        return $this->morphTo();
    }

}
