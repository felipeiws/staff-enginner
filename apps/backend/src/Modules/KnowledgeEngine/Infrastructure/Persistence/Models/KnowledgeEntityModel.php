<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class KnowledgeEntityModel extends Model
{
    protected $table = 'entities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scope' => 'array'];
    }
}
