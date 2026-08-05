import { useEffect, useState, type FormEvent, type ReactNode } from 'react';
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useSearch } from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import type { SearchFacetOption, SearchResult } from '@/data/types';
import './search-page.css';

const organizationId = 'nordika';
const typeFilters = [
  ['Tudo', undefined],
  ['Processos', 'Processo'],
  ['Regras', 'Regra'],
  ['Telas', 'Tela'],
  ['APIs', 'API'],
  ['FAQs', 'FAQ'],
  ['Issues', 'Issue'],
  ['Documentos', 'Documento'],
  ['Vídeos', 'Vídeo'],
] as const;
const facetGroups = [
  ['Aplicação', 'application'],
  ['Frescor', 'freshness'],
  ['Responsável', 'owner'],
] as const;

const resultPath = (result: SearchResult) => {
  const id = result.entity_id || result.id;
  const route = (
    {
      Processo: 'processos',
      Regra: 'regras',
      Tela: 'telas',
      API: 'apis',
      Endpoint: 'apis',
    } as Record<string, string>
  )[result.type.name];
  return route ? `/${route}/${encodeURIComponent(id)}` : '#';
};
const confidenceOf = (result: SearchResult) =>
  Math.round((result.evidence?.confidence ?? result.provenance?.confidence ?? 0) * 100);
const displayCount = (count: number | undefined) => (count ?? 0).toLocaleString('pt-BR');

export function SearchPage() {
  const navigate = useNavigate();
  const location = useLocation();
  const [searchParams, setSearchParams] = useSearchParams();
  const urlQuery = searchParams.get('q') ?? '';
  const [query, setQuery] = useState(urlQuery);
  const entityTypes = searchParams.getAll('entity_type');
  const application = searchParams.get('application');
  const params = {
    q: urlQuery,
    organization: organizationId,
    ...(entityTypes.length ? { entity_type: entityTypes } : {}),
    ...(application ? { application } : {}),
    ...(searchParams.getAll('freshness').length
      ? { freshness: searchParams.getAll('freshness') }
      : {}),
    ...(searchParams.getAll('owner').length ? { owner: searchParams.getAll('owner') } : {}),
  };
  const search = useSearch(params);

  useEffect(() => setQuery(urlQuery), [urlQuery]);
  const updateUrl = (change: (next: URLSearchParams) => void) => {
    const next = new URLSearchParams(searchParams);
    change(next);
    setSearchParams(next);
  };
  const submit = (event: FormEvent) => {
    event.preventDefault();
    updateUrl((next) => {
      const value = query.trim();
      if (value) next.set('q', value);
      else next.delete('q');
    });
  };
  const openCopilot = () => {
    const value = query.trim() || urlQuery;
    if (value) void navigate(`/copiloto?q=${encodeURIComponent(value)}`);
  };
  const toggleType = (type?: string) =>
    updateUrl((next) => {
      next.delete('entity_type');
      if (type) next.append('entity_type', type);
    });
  const toggleFacet = (key: 'application' | 'freshness' | 'owner', value: string) =>
    updateUrl((next) => {
      const current = next.getAll(key);
      next.delete(key);
      current
        .filter((item) => item !== value)
        .concat(current.includes(value) ? [] : [value])
        .forEach((item) => next.append(key, item));
    });
  const results = search.data?.results ?? [];

  return (
    <SearchShell>
      <div className="search-page">
        <form className="search-query" onSubmit={submit} role="search">
          <span aria-hidden="true">⌕</span>
          <input
            aria-label="Consulta de busca"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
          <button type="button" onClick={openCopilot}>
            ✦ pedir uma resposta
          </button>
        </form>
        <div className="search-types" aria-label="Filtrar por tipo">
          {typeFilters.map(([label, type]) => (
            <button
              className={
                (type ? entityTypes.includes(type) : !entityTypes.length) ? 'is-active' : ''
              }
              key={label}
              onClick={() => toggleType(type)}
              type="button"
            >
              {label}
              <small>
                {displayCount(
                  type ? search.data?.type_counts[type] : search.data?.type_counts.Tudo,
                )}
              </small>
            </button>
          ))}
        </div>
        <div className="search-grid">
          <section className="search-results" aria-live="polite">
            {!urlQuery ? (
              <SearchEmpty
                title="Busque conhecimento publicado"
                description="Digite termos, caminhos ou identificadores para encontrar evidências no Atlas."
              />
            ) : null}
            {urlQuery && search.isLoading ? <DataSkeleton lines={7} /> : null}
            {urlQuery && search.error ? (
              <DataError error={search.error} retry={() => void search.refetch()} />
            ) : null}
            {urlQuery && !search.isLoading && !search.error && !results.length ? (
              <SearchEmpty
                title="Nenhum resultado encontrado"
                description="Tente outro termo ou remova filtros para ampliar a busca."
              />
            ) : null}
            {results.length ? (
              <DirectAnswer result={results[0]!} sources={results.length} onOpen={openCopilot} />
            ) : null}
            {results.map((result) => (
              <SearchResultCard key={result.id} result={result} />
            ))}
          </section>
          <aside className="search-facets" aria-label="Facetas da busca">
            {facetGroups.map(([label, key]) => (
              <Facet
                key={key}
                label={label}
                options={search.data?.facets[key] ?? []}
                active={searchParams.getAll(key)}
                onToggle={(value) => toggleFacet(key, value)}
              />
            ))}
            <button
              className="search-graph-link"
              onClick={() => void navigate(`/graph${location.search}`)}
              type="button"
            >
              <strong>
                Ver estes {displayCount(search.data?.type_counts.Tudo)} resultados no Graph
              </strong>
              <span>Útil para achar entidades órfãs e clusters sem responsável.</span>
            </button>
          </aside>
        </div>
      </div>
    </SearchShell>
  );
}

function DirectAnswer({
  result,
  sources,
  onOpen,
}: {
  result: SearchResult;
  sources: number;
  onOpen: () => void;
}) {
  const excerpt = result.excerpt;
  return (
    <button className="search-direct-answer" onClick={onOpen} type="button">
      <span className="search-direct-answer__label">
        ✦ <span>Resposta direta · confiança {confidenceOf(result)}%</span>
      </span>
      <p>
        {excerpt?.pre}
        {excerpt?.hit ? <mark>{excerpt.hit}</mark> : null}
        {excerpt?.post || result.title}
      </p>
      <small>
        {sources} {sources === 1 ? 'fonte' : 'fontes'} · abrir no Copiloto →
      </small>
    </button>
  );
}

function SearchResultCard({ result }: { result: SearchResult }) {
  const excerpt = result.excerpt;
  const destination = resultPath(result);
  const evidence = result.evidence;
  const metadata = [result.metadata, evidence?.source_revision, evidence?.method]
    .filter(Boolean)
    .join(' · ');
  const content = (
    <>
      <div className="search-result__top">
        <span className={`search-result__type search-result__type--${result.type.color}`}>
          {result.type.name}
        </span>
        <span className="search-result__path">{result.path}</span>
        <span className="search-result__freshness">{result.freshness}</span>
      </div>
      <h2>{result.title}</h2>
      <p>
        {excerpt?.pre}
        {excerpt?.hit ? <mark>{excerpt.hit}</mark> : null}
        {excerpt?.post}
      </p>
      <footer>
        <span>{metadata}</span>
        <i />
        {result.related_entities?.map((entity) => (
          <em key={entity}>{entity}</em>
        ))}
      </footer>
    </>
  );
  return destination === '#' ? (
    <article className="search-result">{content}</article>
  ) : (
    <Link className="search-result" to={destination}>
      {content}
    </Link>
  );
}

function Facet({
  label,
  options,
  active,
  onToggle,
}: {
  label: string;
  options: readonly SearchFacetOption[];
  active: readonly string[];
  onToggle: (value: string) => void;
}) {
  return (
    <section className="search-facet">
      <h2>{label}</h2>
      {options.length ? (
        <div>
          {options.map((option) => (
            <button
              className={active.includes(option.value) ? 'is-active' : ''}
              key={option.value}
              onClick={() => onToggle(option.value)}
              type="button"
            >
              {option.value} <small>{option.count}</small>
            </button>
          ))}
        </div>
      ) : (
        <p>Sem opções disponíveis</p>
      )}
    </section>
  );
}

function SearchEmpty({ title, description }: { title: string; description: string }) {
  return (
    <div className="search-empty">
      <h2>{title}</h2>
      <p>{description}</p>
    </div>
  );
}
function SearchShell({ children }: { children: ReactNode }) {
  return (
    <div className="portal-shell">
      <PortalSidebar />
      <main id="conteudo-principal" className="portal-content">
        <PortalHeader />
        {children}
      </main>
    </div>
  );
}
