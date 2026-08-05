import { useEffect, useState, type FormEvent, type KeyboardEvent } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useApprovals } from '@/data/hooks';
import './portal-header.css';

export type SearchDestination = 'search' | 'copilot';

const interrogatives =
  /\b(o que|como|por que|porque|qual|quais|quem|onde|quando|quanto|quantos|quanta|quantas)\b/i;
const technicalIdentifier =
  /(?:\b[A-Z]{2,}[/_-]?\d+[\w-]*\b|\b[A-Z][A-Z0-9_]{2,}\b|\/[\w{}:.-]+|\b(?:GET|POST|PUT|PATCH|DELETE)\b|[.#][\w-]+|\b\w+\.\w+\b)/;

/**
 * Mantém a escolha inicial previsível: identificadores e termos curtos usam
 * a busca facetada; perguntas ou pedidos mais longos chegam ao Copiloto.
 */
export function classifyUnifiedSearch(value: string): SearchDestination {
  const query = value.trim();
  if (!query || technicalIdentifier.test(query)) return 'search';
  if (query.includes('?') || interrogatives.test(query)) return 'copilot';
  return query.length > 48 || query.split(/\s+/).length > 5 ? 'copilot' : 'search';
}

export function breadcrumbForPath(pathname: string, query: string) {
  if (pathname === '/') return 'Organização Nordika';
  if (pathname === '/busca') return `Busca › "${query}"`;
  if (pathname === '/copiloto') return 'Copiloto · escopo ERP Financeiro';
  if (pathname === '/graph') return 'ERP Financeiro › Knowledge Graph';
  if (pathname.startsWith('/aplicacoes/')) return 'ERP Financeiro';
  if (pathname.startsWith('/processos/')) return 'ERP Financeiro › Compras › Processo';
  if (pathname.startsWith('/regras/')) return 'ERP Financeiro › Compras › Regra';
  if (pathname.startsWith('/telas/')) return 'ERP Financeiro › Compras › Tela';
  if (pathname.startsWith('/apis/')) return 'ERP Financeiro › Compras › API';
  if (pathname === '/aprovacoes') return 'Governança › Aprovações';
  if (pathname === '/saude') return 'Governança › Saúde do conhecimento';
  if (pathname === '/decisoes-ux') return 'Decisões de UX';
  return 'Organização Nordika';
}

export function PortalHeader() {
  const location = useLocation();
  const navigate = useNavigate();
  const approvals = useApprovals();
  const routeQuery = new URLSearchParams(location.search).get('q') ?? '';
  const [query, setQuery] = useState(routeQuery);
  const [drawerOpen, setDrawerOpen] = useState(false);

  useEffect(() => setQuery(routeQuery), [routeQuery]);

  const go = (destination: SearchDestination) => {
    const trimmed = query.trim();
    if (!trimmed) return;
    const target = destination === 'search' ? '/busca' : '/copiloto';
    void navigate(`${target}?q=${encodeURIComponent(trimmed)}`);
  };

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    go(classifyUnifiedSearch(query));
  };

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
      event.preventDefault();
      go('search');
    }
  };

  const pending = approvals.data?.approvals.length ?? 0;
  const crumbQuery = pathnameIsSearch(location.pathname) ? routeQuery : query;

  return (
    <header className="portal-header">
      <a className="portal-skip-link" href="#conteudo-principal">Pular para o conteúdo</a>
      <button
        aria-controls="portal-sidebar"
        aria-expanded={drawerOpen}
        aria-label={drawerOpen ? 'Fechar navegação' : 'Abrir navegação'}
        className="portal-header-menu"
        onClick={() => {
          const open = !drawerOpen;
          setDrawerOpen(open);
          window.dispatchEvent(new CustomEvent('atlas:drawer', { detail: open }));
        }}
        type="button"
      >
        ☰
      </button>
      <span className="portal-header-breadcrumb">
        {breadcrumbForPath(location.pathname, crumbQuery)}
      </span>
      <div className="portal-header-search-wrap">
        <form className="portal-header-search" onSubmit={submit} role="search">
          <span className="portal-header-search-icon" aria-hidden="true">
            ⌕
          </span>
          <input
            aria-label="Busque ou pergunte qualquer coisa"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            onKeyDown={onKeyDown}
            placeholder="Busque ou pergunte qualquer coisa…"
          />
          <button type="button" onClick={() => go('copilot')} title="Forçar pergunta ao Copiloto">
            ✦ perguntar
          </button>
        </form>
      </div>
      <div className="portal-header-actions">
        <button
          type="button"
          className="portal-header-approvals"
          onClick={() => void navigate('/aprovacoes')}
        >
          {pending} pendentes
        </button>
        <div className="portal-header-avatar" aria-label="Usuário RS">
          RS
        </div>
      </div>
    </header>
  );
}

const pathnameIsSearch = (pathname: string) => pathname === '/busca';
