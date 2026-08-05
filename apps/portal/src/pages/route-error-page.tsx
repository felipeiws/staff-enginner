import { Link } from 'react-router-dom';
import { PortalSidebar } from '@/components';
import './route-error-page.css';

interface RouteErrorPageProps {
  notFound?: boolean;
}

export function RouteErrorPage({ notFound = false }: RouteErrorPageProps) {
  const missing = notFound;

  return (
    <div className="error-app">
      <PortalSidebar />
      <main className="error-main">
        <header className="error-header">
          <span>Atlas / {missing ? 'não encontrado' : 'erro'}</span>
          <div className="header-search">
            <span>⌕</span>
            <span>Busque ou pergunte qualquer coisa…</span>
            <b>✦ perguntar</b>
          </div>
          <div className="header-actions">
            <span>7 pendentes</span>
            <i>RS</i>
          </div>
        </header>
        <section className="error-content" aria-labelledby="error-title">
          <p className="error-label">{missing ? 'Erro 404' : 'Erro de navegação'}</p>
          <h1 id="error-title">
            {missing ? 'Esta página não foi encontrada' : 'Não foi possível carregar esta página'}
          </h1>
          <p>
            {missing
              ? 'O endereço pode ter mudado ou não existir neste espaço de conhecimento.'
              : 'Tente novamente. Se o problema continuar, consulte a origem e a revisão da solicitação.'}
          </p>
          <div className="error-actions">
            <Link to="/">← voltar ao início</Link>
            <Link to="/busca">⌕ abrir busca</Link>
          </div>
        </section>
      </main>
    </div>
  );
}
