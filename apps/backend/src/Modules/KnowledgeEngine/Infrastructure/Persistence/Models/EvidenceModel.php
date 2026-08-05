<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class EvidenceModel extends Model
{
    protected $table = 'assertion_evidence';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
