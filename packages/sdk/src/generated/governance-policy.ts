/* Gerado de packages/contracts/schemas/v1/governance-policy.json. Não edite manualmente. */

export type Strings = string[];

export interface GovernancePolicyDocument {
  schema_version: '1.0.0';
  /**
   * @minItems 1
   */
  policies: [Policy, ...Policy[]];
}
export interface Policy {
  policy_key: string;
  version: number;
  name: string;
  priority: number;
  active?: boolean;
  effective_from?: string;
  effective_to?: string;
  applies_to: {
    source_types?: Strings;
    entity_types?: Strings;
    classifications?: Strings;
    derivation_methods?: Strings;
    impact_levels?: Strings;
    confidence?: {
      min?: number;
      max?: number;
    };
    scope?: {
      [k: string]: Strings;
    };
    owner?: {
      ids?: Strings;
      roles?: Strings;
    };
  };
  outcome: {
    action: 'publish' | 'review' | 'block';
    approver_roles?: Strings;
    validity_days?: number;
    freshness_warning_days?: number;
    retraction_behavior?: string;
    auto_approve_after_hours?: number;
  };
}
