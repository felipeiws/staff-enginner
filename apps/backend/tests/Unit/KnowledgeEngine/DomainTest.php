<?php

declare(strict_types=1);

use Atlas\Contracts\Enum\GraphPredicate;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\Assertion;
use Atlas\Modules\KnowledgeEngine\Domain\Aggregate\KnowledgeRevision;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Confidence;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\DerivationMethod;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Identifier;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\LifecycleState;
use Atlas\Modules\KnowledgeEngine\Domain\ValueObject\Scope;

function domainId(int $number): Identifier
{
    return new Identifier(sprintf('10000000-0000-4000-8000-%012d', $number));
}

it('rejects confidence outside the closed interval', function (): void {
    new Confidence(0.0);
    new Confidence(1.0);

    expect(fn () => new Confidence(-0.01))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Confidence(1.01))->toThrow(InvalidArgumentException::class);
});

it('preserves lifecycle transitions and rejects terminal transitions', function (): void {
    expect(LifecycleState::Candidate->transitionTo(LifecycleState::Review))->toBe(LifecycleState::Review)
        ->and(fn () => LifecycleState::Retracted->transitionTo(LifecycleState::Published))->toThrow(DomainException::class);
});

it('requires a single assertion object and a coherent validity interval', function (): void {
    expect(fn () => new Assertion(domainId(1), domainId(2), GraphPredicate::Calls, null, null, new Scope(['application' => 'atlas']), new Confidence(0.8), new DerivationMethod('parser@1'), 'worker:parser', 'worker', LifecycleState::Candidate))->toThrow(InvalidArgumentException::class);

    expect(fn () => new Assertion(domainId(1), domainId(2), GraphPredicate::Calls, domainId(3), null, new Scope(['application' => 'atlas']), new Confidence(0.8), new DerivationMethod('parser@1'), 'worker:parser', 'worker', LifecycleState::Candidate, new DateTimeImmutable('2026-01-02'), new DateTimeImmutable('2026-01-01')))->toThrow(InvalidArgumentException::class);
});

it('requires a successor only when a revision is superseded', function (): void {
    expect(fn () => new KnowledgeRevision(domainId(1), domainId(2), 1, LifecycleState::Superseded, 'Título', null, [], domainId(3), new Confidence(0.9)))->toThrow(InvalidArgumentException::class);
});
