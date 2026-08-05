<?php

declare(strict_types=1);

namespace Atlas\Modules\Ingestion\Application;

use DomainException;

final class SourceObservationConflict extends DomainException {}
