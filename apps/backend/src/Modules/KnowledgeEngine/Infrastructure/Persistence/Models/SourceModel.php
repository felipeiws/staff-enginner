<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class SourceModel extends Model
{
    protected $table = 'sources';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scope' => 'array', 'active' => 'boolean'];
    }
}
