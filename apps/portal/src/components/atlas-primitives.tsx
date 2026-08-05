import type { ButtonHTMLAttributes, HTMLAttributes, InputHTMLAttributes, ReactNode } from 'react';
import './atlas-primitives.css';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return <div className={`atlas-card ${className ?? ''}`} {...props} />;
}

export function SectionLabel({ className, ...props }: HTMLAttributes<HTMLSpanElement>) {
  return <span className={`atlas-section-label ${className ?? ''}`} {...props} />;
}

const entityColors = {
  Processo: 'violet',
  Regra: 'warn',
  Tela: 'info',
  API: 'danger',
  Endpoint: 'danger',
  Evento: 'pink',
  Documento: 'sky',
  Aplicação: 'acc',
} as const;

export type EntityType = keyof typeof entityColors;

export function EntityBadge({ type, children }: { type: EntityType; children?: ReactNode }) {
  return (
    <span className={`atlas-entity-badge atlas-entity-badge--${entityColors[type]}`}>
      {children ?? type}
    </span>
  );
}

export type FreshnessState = 'atual' | 'atenção' | 'desatualizado';

const freshnessDefaultText: Record<FreshnessState, string> = {
  atual: 'frescor: revisado há 3 dias',
  atenção: 'frescor: expira em breve',
  desatualizado: 'print desatualizado há 5 meses',
};

export function FreshnessSeal({
  state,
  children,
}: {
  state: FreshnessState;
  children?: ReactNode;
}) {
  return (
    <span className={`atlas-freshness atlas-freshness--${state}`}>
      {children ?? freshnessDefaultText[state]}
    </span>
  );
}

export function ConfidencePill({ confidence, sources }: { confidence: number; sources?: number }) {
  return (
    <span className="atlas-confidence">
      confiança {confidence}%{sources === undefined ? '' : ` · ${sources} fontes`}
    </span>
  );
}

export type ButtonVariant = 'primary' | 'secondary';

export function Button({
  className,
  variant = 'primary',
  type = 'button',
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: ButtonVariant }) {
  return (
    <button
      className={`atlas-button atlas-button--${variant} ${className ?? ''}`}
      type={type}
      {...props}
    />
  );
}

export function SearchInput({
  className,
  action,
  ...props
}: InputHTMLAttributes<HTMLInputElement> & { action?: ReactNode }) {
  return (
    <label className={`atlas-search-input ${className ?? ''}`}>
      <span aria-hidden="true">⌕</span>
      <input type="search" {...props} />
      {action ? <span className="atlas-search-input__action">{action}</span> : null}
    </label>
  );
}

export type Tab = { id: string; label: string };
type TabsProps = {
  tabs: readonly Tab[];
  value: string;
  onChange: (id: string) => void;
  'aria-label': string;
};

function TabsBase({
  tabs,
  value,
  onChange,
  variant,
  'aria-label': ariaLabel,
}: TabsProps & { variant: 'underline' | 'pill' }) {
  return (
    <div className={`atlas-tabs atlas-tabs--${variant}`} role="tablist" aria-label={ariaLabel}>
      {tabs.map((tab) => (
        <button
          aria-controls={`${tab.id}-panel`}
          aria-selected={tab.id === value}
          className="atlas-tabs__tab"
          id={tab.id}
          key={tab.id}
          onClick={() => onChange(tab.id)}
          role="tab"
          type="button"
        >
          {tab.label}
        </button>
      ))}
    </div>
  );
}

export function UnderlineTabs(props: TabsProps) {
  return <TabsBase {...props} variant="underline" />;
}
export function PillTabs(props: TabsProps) {
  return <TabsBase {...props} variant="pill" />;
}

export function ProgressBar({
  value,
  size = 'thin',
  label,
}: {
  value: number;
  size?: 'thin' | 'regular';
  label: string;
}) {
  const boundedValue = Math.min(100, Math.max(0, value));
  return (
    <div
      aria-label={label}
      aria-valuemax={100}
      aria-valuemin={0}
      aria-valuenow={boundedValue}
      className={`atlas-progress atlas-progress--${size}`}
      role="progressbar"
    >
      <span style={{ width: `${boundedValue}%` }} />
    </div>
  );
}

export function Sparkline({
  points,
  color = 'var(--acc)',
  label,
}: {
  points: string;
  color?: string;
  label: string;
}) {
  return (
    <svg aria-label={label} className="atlas-sparkline" role="img" viewBox="0 0 150 34">
      <polyline fill="none" points={points} stroke={color} strokeWidth="1.4" />
    </svg>
  );
}

export function MetaRow({ children, className, ...props }: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={`atlas-meta-row ${className ?? ''}`} {...props}>
      {children}
    </div>
  );
}

export function ChipList({
  items,
  ariaLabel = 'Etiquetas',
}: {
  items: readonly string[];
  ariaLabel?: string;
}) {
  return (
    <ul aria-label={ariaLabel} className="atlas-chip-list">
      {items.map((item) => (
        <li key={item}>{item}</li>
      ))}
    </ul>
  );
}

export function DiffPane({
  previous,
  current,
  previousLabel = 'v3 · 08/11/2025',
  currentLabel = 'v4 · vigente',
}: {
  previous: readonly string[];
  current: readonly string[];
  previousLabel?: string;
  currentLabel?: string;
}) {
  return (
    <div className="atlas-diff-pane">
      <section
        aria-label="Conteúdo removido"
        className="atlas-diff-pane__column atlas-diff-pane__column--old"
      >
        <span>{previousLabel}</span>
        {previous.map((line) => (
          <code className="atlas-diff-pane__line atlas-diff-pane__line--old" key={line}>
            − {line}
          </code>
        ))}
      </section>
      <section aria-label="Conteúdo adicionado" className="atlas-diff-pane__column">
        <span>{currentLabel}</span>
        {current.map((line) => (
          <code className="atlas-diff-pane__line atlas-diff-pane__line--new" key={line}>
            + {line}
          </code>
        ))}
      </section>
    </div>
  );
}

export function EmptyState({
  title,
  description,
  action,
}: {
  title: string;
  description: string;
  action?: ReactNode;
}) {
  return (
    <section className="atlas-empty-state">
      <h2>{title}</h2>
      <p>{description}</p>
      {action}
    </section>
  );
}

export function SkeletonLoader({
  lines = 3,
  label = 'Carregando conteúdo',
}: {
  lines?: number;
  label?: string;
}) {
  return (
    <div aria-busy="true" aria-label={label} className="atlas-skeleton" role="status">
      {Array.from({ length: lines }, (_, index) => (
        <span key={index} />
      ))}
      <span className="atlas-visually-hidden">{label}</span>
    </div>
  );
}
