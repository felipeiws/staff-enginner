import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
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

describe('primitivos visuais Atlas', () => {
  it('expõe estrutura semântica e nomes acessíveis', () => {
    render(
      <>
        <Card>Conteúdo</Card>
        <SectionLabel>Fontes</SectionLabel>
        <EntityBadge type="Processo" />
        <FreshnessSeal state="atual" />
        <ConfidencePill confidence={92} sources={4} />
        <Button>Enviar</Button>
        <Button variant="secondary">Cancelar</Button>
        <SearchInput aria-label="Buscar conhecimento" />
        <ProgressBar label="Cobertura" size="regular" value={74} />
        <Sparkline label="Tendência" points="0,1 150,20" />
        <MetaRow>GitHub · a91f3ce</MetaRow>
        <ChipList items={['ERP Financeiro']} />
        <EmptyState description="Sem itens." title="Nenhum resultado" />
        <SkeletonLoader />
      </>,
    );
    expect(screen.getByText('Conteúdo')).toHaveClass('atlas-card');
    expect(screen.getByText('Fontes')).toHaveClass('atlas-section-label');
    expect(screen.getByText('Processo')).toHaveTextContent('Processo');
    expect(screen.getByText(/frescor: revisado/i)).toBeInTheDocument();
    expect(screen.getByText('confiança 92% · 4 fontes')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Enviar' })).toBeEnabled();
    expect(screen.getByRole('searchbox', { name: 'Buscar conhecimento' })).toBeInTheDocument();
    expect(screen.getByRole('progressbar', { name: 'Cobertura' })).toHaveAttribute(
      'aria-valuenow',
      '74',
    );
    expect(screen.getByRole('img', { name: 'Tendência' })).toHaveAttribute('viewBox', '0 0 150 34');
    expect(screen.getByRole('list', { name: 'Etiquetas' })).toHaveTextContent('ERP Financeiro');
    expect(screen.getByRole('heading', { name: 'Nenhum resultado' })).toBeInTheDocument();
    expect(screen.getByRole('status', { name: 'Carregando conteúdo' })).toHaveAttribute(
      'aria-busy',
      'true',
    );
  });

  it('permite selecionar abas por controles nativos e oferece painéis nomeados', () => {
    const onChange = vi.fn();
    render(
      <>
        <UnderlineTabs
          aria-label="Seções"
          onChange={onChange}
          tabs={[
            { id: 'resumo', label: 'Resumo' },
            { id: 'fontes', label: 'Fontes' },
          ]}
          value="resumo"
        />
        <PillTabs
          aria-label="Visualização"
          onChange={onChange}
          tabs={[
            { id: 'tecnica', label: 'Técnica' },
            { id: 'negocio', label: 'Negócio' },
          ]}
          value="tecnica"
        />
      </>,
    );
    const fontes = screen.getByRole('tab', { name: 'Fontes' });
    expect(fontes).toHaveAttribute('aria-selected', 'false');
    fireEvent.click(fontes);
    expect(onChange).toHaveBeenCalledWith('fontes');
    expect(screen.getByRole('tablist', { name: 'Visualização' })).toBeInTheDocument();
  });

  it('mantém o diff distinguível em texto e regiões, sem depender só de cor', () => {
    render(<DiffPane current={['Novo texto']} previous={['Texto removido']} />);
    expect(screen.getByRole('region', { name: 'Conteúdo removido' })).toHaveTextContent(
      '− Texto removido',
    );
    expect(screen.getByRole('region', { name: 'Conteúdo adicionado' })).toHaveTextContent(
      '+ Novo texto',
    );
  });
});
