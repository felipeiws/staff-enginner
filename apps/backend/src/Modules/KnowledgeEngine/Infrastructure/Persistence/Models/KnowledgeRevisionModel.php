<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class KnowledgeRevisionModel extends Model
{
    protected $table = 'entity_revisions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['attributes' => 'array', 'published_at' => 'immutable_datetime'];
    }
}
