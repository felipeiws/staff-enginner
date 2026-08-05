import Ajv2020Module, { type ErrorObject, type ValidateFunction } from 'ajv/dist/2020.js';
import addFormatsModule from 'ajv-formats';

import approvalSchema from '../../contracts/schemas/v1/approval.json' with { type: 'json' };
import assertionSchema from '../../contracts/schemas/v1/assertion.json' with { type: 'json' };
import auditEntrySchema from '../../contracts/schemas/v1/audit-entry.json' with { type: 'json' };
import domainEventSchema from '../../contracts/schemas/v1/domain-event.json' with { type: 'json' };
import evidenceSchema from '../../contracts/schemas/v1/evidence.json' with { type: 'json' };
import knowledgeEntitySchema from '../../contracts/schemas/v1/knowledge-entity.json' with { type: 'json' };
import knowledgeRevisionSchema from '../../contracts/schemas/v1/knowledge-revision.json' with { type: 'json' };
import sourceObservationSchema from '../../contracts/schemas/v1/source-observation.json' with { type: 'json' };
import type { ApprovalV1 } from './generated/approval.js';
import type { AssertionV1 } from './generated/assertion.js';
import type { AuditEntryV1 } from './generated/audit-entry.js';
import type { DomainEventEnvelopeV1 } from './generated/domain-event.js';
import type { EvidenceV1 } from './generated/evidence.js';
import type { KnowledgeEntityV1 } from './generated/knowledge-entity.js';
import type { KnowledgeRevisionV1 } from './generated/knowledge-revision.js';
import type { SourceObservationV1 } from './generated/source-observation.js';

export type ContractName =
  | 'approval'
  | 'assertion'
  | 'audit-entry'
  | 'domain-event'
  | 'evidence'
  | 'knowledge-entity'
  | 'knowledge-revision'
  | 'source-observation';
export type ContractTypeByName = {
  approval: ApprovalV1;
  assertion: AssertionV1;
  'audit-entry': AuditEntryV1;
  'domain-event': DomainEventEnvelopeV1;
  evidence: EvidenceV1;
  'knowledge-entity': KnowledgeEntityV1;
  'knowledge-revision': KnowledgeRevisionV1;
  'source-observation': SourceObservationV1;
};

type AjvCompiler = { compile(schema: unknown): ValidateFunction };
type AjvConstructor = new (options: {
  allErrors: boolean;
  strict: boolean;
  strictRequired: boolean;
}) => AjvCompiler;

// Ajv publica estes módulos CommonJS; o cast preserva sua superfície runtime em consumidores ESM.
const Ajv2020 = Ajv2020Module as unknown as AjvConstructor;
const addFormats = addFormatsModule as unknown as (instance: AjvCompiler) => AjvCompiler;
// Alguns schemas expressam a alternativa de `object`/`value` em subschemas
// `oneOf`. `strictRequired` não consegue enxergar a propriedade do schema pai,
// embora a construção seja válida no Draft 2020-12; as demais verificações
// estritas permanecem habilitadas.
const ajv = addFormats(new Ajv2020({ allErrors: true, strict: true, strictRequired: false }));
const schemas = {
  approval: approvalSchema,
  assertion: assertionSchema,
  'audit-entry': auditEntrySchema,
  'domain-event': domainEventSchema,
  evidence: evidenceSchema,
  'knowledge-entity': knowledgeEntitySchema,
  'knowledge-revision': knowledgeRevisionSchema,
  'source-observation': sourceObservationSchema,
} as const;

export const contractValidators: Readonly<Record<ContractName, ValidateFunction>> =
  Object.fromEntries(
    Object.entries(schemas).map(([name, schema]) => [name, ajv.compile(schema)]),
  ) as Readonly<Record<ContractName, ValidateFunction>>;

export type ValidationResult<T> =
  | { readonly valid: true; readonly data: T; readonly errors: readonly [] }
  | { readonly valid: false; readonly errors: readonly ErrorObject[] };

export function validateContract<Name extends ContractName>(
  name: Name,
  value: unknown,
): ValidationResult<ContractTypeByName[Name]> {
  const validator = contractValidators[name];
  if (validator(value)) return { valid: true, data: value as ContractTypeByName[Name], errors: [] };
  return { valid: false, errors: validator.errors ?? [] };
}

function isContract<Name extends ContractName>(
  name: Name,
  value: unknown,
): value is ContractTypeByName[Name] {
  return contractValidators[name](value);
}
export const isApproval = (value: unknown): value is ApprovalV1 => isContract('approval', value);
export const isAssertion = (value: unknown): value is AssertionV1 => isContract('assertion', value);
export const isAuditEntry = (value: unknown): value is AuditEntryV1 =>
  isContract('audit-entry', value);
export const isDomainEvent = (value: unknown): value is DomainEventEnvelopeV1 =>
  isContract('domain-event', value);
export const isEvidence = (value: unknown): value is EvidenceV1 => isContract('evidence', value);
export const isKnowledgeEntity = (value: unknown): value is KnowledgeEntityV1 =>
  isContract('knowledge-entity', value);
export const isKnowledgeRevision = (value: unknown): value is KnowledgeRevisionV1 =>
  isContract('knowledge-revision', value);
export const isSourceObservation = (value: unknown): value is SourceObservationV1 =>
  isContract('source-observation', value);
