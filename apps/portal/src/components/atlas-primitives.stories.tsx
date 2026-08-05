import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState, type ReactNode } from 'react';
import {
  Button,
  Card,
  ChipList,
  ConfidencePill,
  DiffPane,
  EmptyState,
  EntityBadge,
  FreshnessSeal,
  MetaRow,
  PillTabs,
  ProgressBar,
  SearchInput,
  SectionLabel,
  SkeletonLoader,
  Sparkline,
  UnderlineTabs,
} from './atlas-primitives';

function Theme({ theme, children }: { theme: 'light' | 'dark'; children: ReactNode }) {
  return (
    <div
      data-theme={theme}
      style={{ background: 'var(--bg)', color: 'var(--tx)', minHeight: 180, padding: 24 }}
    >
      {children}
    </div>
  );
}
function StatefulTabs({ variant }: { variant: 'underline' | 'pill' }) {
  const [value, setValue] = useState('resumo');
  const props = {
    'aria-label': 'Navegação do processo',
    onChange: setValue,
    tabs: [
      { id: 'resumo', label: 'Resumo' },
      { id: 'fluxo', label: 'Fluxo' },
    ],
    value,
  } as const;
  return variant === 'underline' ? <UnderlineTabs {...props} /> : <PillTabs {...props} />;
}
const meta: Meta = { title: 'Componentes/Primitivos', parameters: { layout: 'fullscreen' } };
export default meta;
type Story = StoryObj;

export const CardEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <Card>Conteúdo do card</Card>
    </Theme>
  ),
};
export const CardClaro: Story = {
  render: () => (
    <Theme theme="light">
      <Card>Conteúdo do card</Card>
    </Theme>
  ),
};
export const SectionLabelEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <SectionLabel>Responsáveis</SectionLabel>
    </Theme>
  ),
};
export const SectionLabelClaro: Story = {
  render: () => (
    <Theme theme="light">
      <SectionLabel>Responsáveis</SectionLabel>
    </Theme>
  ),
};
export const EntityBadgeEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <EntityBadge type="Processo" />
    </Theme>
  ),
};
export const EntityBadgeClaro: Story = {
  render: () => (
    <Theme theme="light">
      <EntityBadge type="Processo" />
    </Theme>
  ),
};
export const FreshnessSealEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <FreshnessSeal state="atual" /> <FreshnessSeal state="atenção" />{' '}
      <FreshnessSeal state="desatualizado" />
    </Theme>
  ),
};
export const FreshnessSealClaro: Story = {
  render: () => (
    <Theme theme="light">
      <FreshnessSeal state="atual" /> <FreshnessSeal state="atenção" />{' '}
      <FreshnessSeal state="desatualizado" />
    </Theme>
  ),
};
export const ConfidencePillEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <ConfidencePill confidence={92} sources={4} />
    </Theme>
  ),
};
export const ConfidencePillClaro: Story = {
  render: () => (
    <Theme theme="light">
      <ConfidencePill confidence={92} sources={4} />
    </Theme>
  ),
};
export const ButtonsEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <Button>✦ Perguntar</Button> <Button variant="secondary">Abrir no Graph</Button>
    </Theme>
  ),
};
export const ButtonsClaro: Story = {
  render: () => (
    <Theme theme="light">
      <Button>✦ Perguntar</Button> <Button variant="secondary">Abrir no Graph</Button>
    </Theme>
  ),
};
export const SearchInputEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <SearchInput
        action="✦ perguntar"
        aria-label="Buscar"
        placeholder="Busque ou pergunte qualquer coisa…"
      />
    </Theme>
  ),
};
export const SearchInputClaro: Story = {
  render: () => (
    <Theme theme="light">
      <SearchInput
        action="✦ perguntar"
        aria-label="Buscar"
        placeholder="Busque ou pergunte qualquer coisa…"
      />
    </Theme>
  ),
};
export const UnderlineTabsEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <StatefulTabs variant="underline" />
    </Theme>
  ),
};
export const UnderlineTabsClaro: Story = {
  render: () => (
    <Theme theme="light">
      <StatefulTabs variant="underline" />
    </Theme>
  ),
};
export const PillTabsEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <StatefulTabs variant="pill" />
    </Theme>
  ),
};
export const PillTabsClaro: Story = {
  render: () => (
    <Theme theme="light">
      <StatefulTabs variant="pill" />
    </Theme>
  ),
};
export const ProgressBarsEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <ProgressBar label="Cobertura" value={74} />
      <br />
      <ProgressBar label="Dívida" size="regular" value={42} />
    </Theme>
  ),
};
export const ProgressBarsClaro: Story = {
  render: () => (
    <Theme theme="light">
      <ProgressBar label="Cobertura" value={74} />
      <br />
      <ProgressBar label="Dívida" size="regular" value={42} />
    </Theme>
  ),
};
export const SparklineEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <Sparkline label="Tendência de saúde" points="0,28 20,22 45,25 70,14 92,18 120,8 150,5" />
    </Theme>
  ),
};
export const SparklineClaro: Story = {
  render: () => (
    <Theme theme="light">
      <Sparkline label="Tendência de saúde" points="0,28 20,22 45,25 70,14 92,18 120,8 150,5" />
    </Theme>
  ),
};
export const MetaRowEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <MetaRow>GitHub · a91f3ce · há 4 min</MetaRow>
    </Theme>
  ),
};
export const MetaRowClaro: Story = {
  render: () => (
    <Theme theme="light">
      <MetaRow>GitHub · a91f3ce · há 4 min</MetaRow>
    </Theme>
  ),
};
export const ChipListEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <ChipList items={['ERP Financeiro', 'Estoque', 'CI']} />
    </Theme>
  ),
};
export const ChipListClaro: Story = {
  render: () => (
    <Theme theme="light">
      <ChipList items={['ERP Financeiro', 'Estoque', 'CI']} />
    </Theme>
  ),
};
export const DiffPaneEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <DiffPane
        previous={['Permite cancelar até faturamento parcial']}
        current={['Permite cancelar saldo não faturado']}
      />
    </Theme>
  ),
};
export const DiffPaneClaro: Story = {
  render: () => (
    <Theme theme="light">
      <DiffPane
        previous={['Permite cancelar até faturamento parcial']}
        current={['Permite cancelar saldo não faturado']}
      />
    </Theme>
  ),
};
export const EmptyStateEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <EmptyState
        description="Tente ajustar os filtros ou consulte outra fonte."
        title="Nenhum resultado encontrado"
      />
    </Theme>
  ),
};
export const EmptyStateClaro: Story = {
  render: () => (
    <Theme theme="light">
      <EmptyState
        description="Tente ajustar os filtros ou consulte outra fonte."
        title="Nenhum resultado encontrado"
      />
    </Theme>
  ),
};
export const SkeletonLoaderEscuro: Story = {
  render: () => (
    <Theme theme="dark">
      <SkeletonLoader />
    </Theme>
  ),
};
export const SkeletonLoaderClaro: Story = {
  render: () => (
    <Theme theme="light">
      <SkeletonLoader />
    </Theme>
  ),
};
