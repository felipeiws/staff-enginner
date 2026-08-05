<?php

declare(strict_types=1);

namespace Atlas\Modules\Copilot\Domain;

interface ModelProvider
{
    public function generate(ModelRequest $request): ModelResponse;
}
