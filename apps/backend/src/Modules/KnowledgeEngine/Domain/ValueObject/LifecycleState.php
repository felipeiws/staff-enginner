<?php

declare(strict_types=1);

namespace Atlas\Modules\KnowledgeEngine\Domain\ValueObject;

use DomainException;

enum LifecycleState: string
{
    case Observed = 'observed';
    case Draft = 'draft';
    case Candidate = 'candidate';
    case Review = 'review';
    case Published = 'published';
    case Superseded = 'superseded';
    case Retracted = 'retracted';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Observed => in_array($next, [self::Candidate, self::Retracted], true),
            self::Draft => in_array($next, [self::Candidate, self::Retracted], true),
            self::Candidate => in_array($next, [self::Review, self::Published, self::Retracted], true),
            self::Review => in_array($next, [self::Published, self::Retracted], true),
            self::Published => in_array($next, [self::Superseded, self::Retracted], true),
            self::Superseded, self::Retracted => false,
        };
    }

    public function transitionTo(self $next): self
    {
        if (! $this->canTransitionTo($next)) {
            throw new DomainException("Transição de ciclo de vida inválida: {$this->value} → {$next->value}.");
        }

        return $next;
    }
}
