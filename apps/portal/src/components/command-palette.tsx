import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useSearch } from '@/data/hooks';
import type { SearchResult } from '@/data/types';
import './command-palette.css';
type PaletteItem = { id: string; type: string; name: string; color: string; run: () => void };
const routeForEntity = (entity: SearchResult) => {
  const base: Record<string, string> = { Processo: '/processos', Regra: '/regras', Tela: '/telas', Endpoint: '/apis', API: '/apis' };
  return base[entity.type.name]
    ? `${base[entity.type.name]}/${encodeURIComponent(entity.entity_id ?? entity.id)}`
    : `/busca?q=${encodeURIComponent(entity.title)}`;
};
const typeColor = (color: string) => color.startsWith('var(') || color.startsWith('#') ? color : `var(--${color})`;
export function CommandPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
  const navigate = useNavigate(); const inputRef = useRef<HTMLInputElement>(null);
  const [query, setQuery] = useState(''); const [debounced, setDebounced] = useState(''); const [focused, setFocused] = useState(0);
  const search = useSearch({ q: debounced, organization: 'nordika', page_size: 8 });
  useEffect(() => { if (!open) return; setQuery(''); setDebounced(''); setFocused(0); requestAnimationFrame(() => inputRef.current?.focus()); }, [open]);
  useEffect(() => { const timeout = window.setTimeout(() => setDebounced(query.trim()), 220); return () => window.clearTimeout(timeout); }, [query]);
  const items = useMemo<readonly PaletteItem[]>(() => {
    const text = query.trim(); const lower = text.toLocaleLowerCase('pt-BR'); const go = (to: string) => () => { void navigate(to); onClose(); };
    const entities = (search.data?.results ?? []).map((entity) => ({ id: `entity-${entity.id}`, type: entity.type.name, name: entity.title, color: typeColor(entity.type.color), run: go(routeForEntity(entity)) }));
    const actions = [
      { id: 'copilot', type: 'Ação', name: text ? `Perguntar ao Copiloto: ${text}` : 'Perguntar ao Copiloto', color: 'var(--violet)', run: go(`/copiloto${text ? `?q=${encodeURIComponent(text)}` : ''}`) },
      { id: 'graph', type: 'Ação', name: 'Abrir o Knowledge Graph', color: 'var(--sky)', run: go('/graph') },
      { id: 'approvals', type: 'Ação', name: 'Revisar pendências', color: 'var(--warn)', run: go('/aprovacoes') },
      { id: 'home', type: 'Painel', name: 'Home', color: 'var(--acc)', run: go('/') },
      { id: 'health', type: 'Painel', name: 'Saúde do conhecimento', color: 'var(--info)', run: go('/saude') },
      { id: 'ux', type: 'Painel', name: 'Decisões de UX', color: 'var(--violet)', run: go('/decisoes-ux') },
    ].filter((item) => !lower || item.name.toLocaleLowerCase('pt-BR').includes(lower));
    return [...entities, ...actions];
  }, [navigate, onClose, query, search.data?.results]);
  useEffect(() => setFocused((index) => Math.min(index, Math.max(0, items.length - 1))), [items.length]);
  if (!open) return null;
  const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') { event.preventDefault(); setFocused((index) => (index + 1) % Math.max(1, items.length)); }
    if (event.key === 'ArrowUp') { event.preventDefault(); setFocused((index) => (index - 1 + Math.max(1, items.length)) % Math.max(1, items.length)); }
    if (event.key === 'Enter' && items[focused]) { event.preventDefault(); items[focused].run(); }
  };
  return <div className="command-palette-overlay" role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}><section className="command-palette" role="dialog" aria-modal="true" aria-label="Paleta de comandos"><div className="command-palette-input-wrap"><span aria-hidden="true">⌕</span><input ref={inputRef} value={query} onChange={(event) => setQuery(event.target.value)} onKeyDown={onKeyDown} placeholder="Ir para entidade, ação ou painel…" aria-label="Ir para entidade, ação ou painel" /><kbd>esc</kbd></div><div className="command-palette-results" role="listbox" aria-label="Resultados da paleta">{items.map((item, index) => <button key={item.id} className={`command-palette-item${focused === index ? ' is-focused' : ''}`} type="button" role="option" aria-selected={focused === index} onMouseEnter={() => setFocused(index)} onClick={item.run}><span style={{ color: item.color }}>{item.type}</span><strong>{item.name}</strong><i aria-hidden="true">↵</i></button>)}</div><footer>Digite uma pergunta para enviar ao Copiloto · ⌘K abre e fecha</footer></section></div>;
}
