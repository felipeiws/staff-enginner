<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class AssertionModel extends Model
{
    protected $table = 'assertions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['literal_value' => 'array', 'scope' => 'array', 'valid_from' => 'immutable_datetime', 'valid_to' => 'immutable_datetime'];
    }
}
