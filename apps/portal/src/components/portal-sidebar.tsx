import { useEffect, useState } from 'react';
import { Link, useLocation, useParams } from 'react-router-dom';
import { useApplication, useApplicationTree, useApprovals, useOrganization } from '@/data/hooks';
import type { ApplicationTreeGroup } from '@/data/types';
import './portal-sidebar.css';
import { CommandPalette } from './command-palette';
import { applyTheme, preferredTheme } from './theme';

type SidebarItem = {
  label: string;
  icon: string;
  to: string;
  badge?: string | number | undefined;
};

const discovery: readonly SidebarItem[] = [
  { label: 'Home', icon: '◈', to: '/' },
  { label: 'Busca', icon: '⌕', to: '/busca' },
  { label: 'Copiloto', icon: '✦', to: '/copiloto', badge: 'IA' },
  { label: 'Knowledge Graph', icon: '⬡', to: '/graph' },
];

const governance: readonly SidebarItem[] = [
  { label: 'Saúde do conhecimento', icon: '◷', to: '/saude' },
  { label: 'Decisões de UX', icon: '✎', to: '/decisoes-ux' },
];

const groupCount = (groups: readonly ApplicationTreeGroup[] | undefined, key: string) =>
  groups?.find((group) => group.key === key)?.count;

const relativeTime = (processedAt?: string) => {
  if (!processedAt) return 'Última indexação indisponível';
  const minutes = Math.max(0, Math.floor((Date.now() - new Date(processedAt).getTime()) / 60_000));
  if (minutes < 1) return 'há menos de 1 min';
  if (minutes < 60) return `há ${minutes} min`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `há ${hours} h`;
  return `há ${Math.floor(hours / 24)} d`;
};

function SidebarLink({ item }: { item: SidebarItem }) {
  const location = useLocation();
  const active = item.to === '/' ? location.pathname === '/' : location.pathname.startsWith(item.to);
  return (
    <Link className={`portal-sidebar-link${active ? ' is-active' : ''}`} to={item.to} aria-current={active ? 'page' : undefined}>
      <span className="portal-sidebar-active-bar" aria-hidden="true" />
      <span className="portal-sidebar-icon" aria-hidden="true">{item.icon}</span>
      <span className="portal-sidebar-link-label">{item.label}</span>
      {item.badge !== undefined ? <span className="portal-sidebar-badge">{item.badge}</span> : null}
    </Link>
  );
}

export function PortalSidebar() {
  const { id } = useParams();
  const location = useLocation();
  const applicationId = location.pathname.startsWith('/aplicacoes/') ? id ?? '' : '';
  const organization = useOrganization('nordika');
  const application = useApplication(applicationId);
  const functionalTree = useApplicationTree(applicationId, 'functional');
  const technicalTree = useApplicationTree(applicationId, 'technical');
  const approvals = useApprovals();
  const [paletteOpen, setPaletteOpen] = useState(false);
  const [drawerOpen, setDrawerOpen] = useState(false);

  useEffect(() => {
    const openWithShortcut = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        setPaletteOpen((open) => !open);
      }
      if (event.key === 'Escape') setPaletteOpen(false);
    };
    window.addEventListener('keydown', openWithShortcut);
    return () => window.removeEventListener('keydown', openWithShortcut);
  }, []);

  useEffect(() => {
    const updateDrawer = (event: Event) => setDrawerOpen(Boolean((event as CustomEvent<boolean>).detail));
    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setDrawerOpen(false);
    };
    window.addEventListener('atlas:drawer', updateDrawer);
    window.addEventListener('keydown', closeOnEscape);
    return () => {
      window.removeEventListener('atlas:drawer', updateDrawer);
      window.removeEventListener('keydown', closeOnEscape);
    };
  }, []);

  useEffect(() => setDrawerOpen(false), [location.pathname]);

  const pendingApprovals = approvals.data?.approvals.length;
  const applicationItems: readonly SidebarItem[] = applicationId
    ? [
        { label: 'Visão da aplicação', icon: '▤', to: `/aplicacoes/${applicationId}` },
        { label: 'Processos', icon: '⇄', to: `/aplicacoes/${applicationId}`, badge: groupCount(functionalTree.data?.groups, 'processes') },
        { label: 'Regras de negócio', icon: '⚖', to: `/aplicacoes/${applicationId}`, badge: groupCount(functionalTree.data?.groups, 'business_rules') },
        { label: 'Telas', icon: '▢', to: `/aplicacoes/${applicationId}`, badge: groupCount(functionalTree.data?.groups, 'screens') ?? application.data?.screens_count },
        { label: 'APIs', icon: '⌗', to: `/aplicacoes/${applicationId}`, badge: groupCount(technicalTree.data?.groups, 'apis') },
      ]
    : [];
  const indexed = organization.data?.provenance;

  return (
    <aside id="portal-sidebar" className={`portal-sidebar${drawerOpen ? ' is-drawer-open' : ''}`} aria-label="Navegação principal">
      <Link to="/" className="portal-sidebar-brand" aria-label="Atlas, página inicial">
        <span className="portal-sidebar-mark">A</span>
        <span className="portal-sidebar-name"><strong>Atlas</strong><small>nordika.knowledge</small></span>
      </Link>
      <div className="portal-sidebar-command-wrap">
        <button id="command-palette-trigger" className="portal-sidebar-command" type="button" aria-haspopup="dialog" aria-expanded={paletteOpen} aria-label="Abrir paleta de comandos" onClick={() => setPaletteOpen(true)}>
          <span>Ir para…</span><kbd>⌘K</kbd>
        </button>
      </div>
      <nav className="portal-sidebar-nav" aria-label="Seções do Atlas" onClick={() => setDrawerOpen(false)}>
        <section className="portal-sidebar-group" aria-labelledby="discover-label">
          <h2 id="discover-label">Descobrir</h2>
          {discovery.map((item) => <SidebarLink key={item.label} item={item} />)}
        </section>
        {applicationItems.length ? <section className="portal-sidebar-group" aria-labelledby="application-label">
          <h2 id="application-label">{application.data?.name ?? 'Aplicação'}</h2>
          {applicationItems.map((item) => <SidebarLink key={item.label} item={item} />)}
        </section> : null}
        <section className="portal-sidebar-group" aria-labelledby="governance-label">
          <h2 id="governance-label">Governança</h2>
          <SidebarLink item={{ label: 'Aprovações', icon: '✓', to: '/aprovacoes', badge: pendingApprovals }} />
          {governance.map((item) => <SidebarLink key={item.label} item={item} />)}
        </section>
      </nav>
      <div className="portal-sidebar-status" aria-live="polite">
        <span className="portal-sidebar-status-dot" aria-hidden="true" />
        <span>{indexed ? `${indexed.method} indexou ${relativeTime(indexed.processed_at)}` : 'Última indexação indisponível'}</span>
        <ThemeToggle />
      </div>
      <CommandPalette open={paletteOpen} onClose={() => setPaletteOpen(false)} />
    </aside>
  );
}

function ThemeToggle() {
  const [theme, setTheme] = useState(() => preferredTheme());
  useEffect(() => applyTheme(theme), [theme]);
  const toggle = () => {
    setTheme((current) => current === 'light' ? 'dark' : 'light');
  };
  return <button type="button" onClick={toggle} aria-label={`Ativar tema ${theme === 'light' ? 'escuro' : 'claro'}`}>{theme === 'light' ? 'escuro' : 'claro'}</button>;
}
