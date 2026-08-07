import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button, ProgressBar, SectionLabel } from '@/components';
import './onboarding-page.css';

const steps = ['Fontes', 'Escopo', 'Responsáveis', 'Indexação'];

const sources = [
  { id: 'git', icon: '⌥', name: 'Repositório Git', description: 'Código-fonte, commits e pull requests' },
  { id: 'tickets', icon: '☰', name: 'Sistema de tickets', description: 'Chamados, bugs e histórico de suporte' },
  { id: 'wiki', icon: '▤', name: 'Wiki interna', description: 'Documentação de processos e políticas' },
  { id: 'openapi', icon: '{ }', name: 'Contrato de API (OpenAPI)', description: 'Endpoints, schemas e contratos publicados' },
  { id: 'confluence', icon: '◫', name: 'Confluence', description: 'Especificações e decisões de arquitetura' },
  { id: 'slack', icon: '#', name: 'Slack', description: 'Canais de discussão técnica e decisões' },
  { id: 'figma', icon: '◆', name: 'Figma', description: 'Telas, fluxos e componentes de design' },
  { id: 'database', icon: '▦', name: 'Banco de dados', description: 'Esquemas, tabelas e relacionamentos' },
];

const scopes = [
  { id: 'erp', name: 'ERP Financeiro', modules: 14, eta: '20–40 min', repo: 'erp-financeiro/core' },
  { id: 'ecommerce', name: 'Plataforma de E-commerce', modules: 9, eta: '15–30 min', repo: 'loja/storefront' },
  { id: 'crm', name: 'CRM de Vendas', modules: 6, eta: '10–20 min', repo: 'vendas/crm-api' },
  { id: 'rh', name: 'Portal de RH', modules: 5, eta: '10–15 min', repo: 'rh/portal-interno' },
];

const owners = [
  { domain: 'Financeiro', email: 'ana.reis@empresa.com', initials: 'AR', role: 'Owner' },
  { domain: 'Vendas', email: 'bruno.melo@empresa.com', initials: 'BM', role: 'Owner' },
  { domain: 'Logística', email: 'carla.dias@empresa.com', initials: 'CD', role: 'Revisor' },
  { domain: 'RH', email: 'diego.souza@empresa.com', initials: 'DS', role: 'Revisor' },
  { domain: 'Plataforma', email: 'elisa.nunes@empresa.com', initials: 'EN', role: 'Owner' },
];

const indexJobs = [
  { id: 'fin', label: 'Indexando módulo Financeiro', status: 'active', meta: '62%' },
  { id: 'rules', label: 'Extraindo regras de negócio', status: 'active', meta: '48%' },
  { id: 'endpoints', label: 'Mapeando endpoints da API', status: 'done', meta: 'concluído' },
  { id: 'screens', label: 'Catalogando telas e fluxos', status: 'pending', meta: 'na fila' },
  { id: 'owners', label: 'Vinculando responsáveis por domínio', status: 'done', meta: 'concluído' },
];

const nextSteps = [
  { id: 'copilot', icon: '✦', title: 'Pergunte ao Copiloto', description: 'Tire dúvidas sobre o que já foi indexado, sempre com resposta e fonte.' },
  { id: 'graph', icon: '◎', title: 'Veja o Knowledge Graph', description: 'Explore como processos, regras e telas se conectam.' },
  { id: 'invite', icon: '⌘', title: 'Convide seu time', description: 'Traga os responsáveis por domínio para revisar propostas.' },
];

const stepEyebrows = ['Passo 1 de 4', 'Passo 2 de 4', 'Passo 3 de 4', 'Passo 4 de 4'];
const stepTitles = ['Conecte as fontes de verdade', 'Escolha o escopo inicial', 'Defina quem responde por cada domínio', 'A indexação já começou'];
const stepIntros = [
  'O Atlas lê o que já existe: repositórios, tickets, wikis e contratos de API. Nada é reescrito — cada resposta aponta de volta para a origem.',
  'Comece por uma aplicação em vez de tudo de uma vez: a primeira indexação completa leva de 20 a 40 minutos por sistema.',
  'Todo conhecimento sem dono vira dívida. Estes responsáveis recebem as propostas da IA para aprovar ou recusar.',
  'Você já pode usar o Atlas enquanto a indexação continua. As respostas ganham cobertura à medida que cada fonte é processada.',
];
const stepHints = [
  'Você poderá conectar mais fontes depois, em Configurações.',
  'Dá para expandir o escopo a qualquer momento.',
  'Só quem você definir aqui recebe propostas para aprovar.',
  'A indexação continua em segundo plano.',
];
const ctaLabels = ['Continuar', 'Continuar', 'Continuar', 'Concluir'];

export function OnboardingPage() {
  const navigate = useNavigate();
  const [step, setStep] = useState(0);
  const [connectedSources, setConnectedSources] = useState<Set<string>>(new Set());
  const [selectedScope, setSelectedScope] = useState('');

  const toggleSource = (id: string) => setConnectedSources((current) => {
    const next = new Set(current);
    if (next.has(id)) next.delete(id); else next.add(id);
    return next;
  });
  const finish = () => void navigate('/');
  const handleNext = () => step < 3 ? setStep(step + 1) : finish();
  const handleBack = () => step > 0 && setStep(step - 1);

  return (
    <div className="onboarding-page">
      <header className="onboarding-header">
        <div className="onboarding-brand">
          <span className="onboarding-brand__mark">A</span>
          <span className="onboarding-brand__name">Atlas</span>
        </div>
        <div className="onboarding-steps">
          {steps.map((label, index) => (
            <button
              key={label}
              type="button"
              className={`onboarding-step-pill${index === step ? ' is-current' : ''}`}
              onClick={() => setStep(index)}
            >
              <span className="onboarding-step-pill__dot">{index < step ? '✓' : index + 1}</span>
              <span>{label}</span>
            </button>
          ))}
        </div>
        <button type="button" className="onboarding-skip" onClick={finish}>pular configuração →</button>
      </header>

      <div className="onboarding-main">
        <div className="onboarding-content">
          <SectionLabel className="onboarding-eyebrow">{stepEyebrows[step]}</SectionLabel>
          <h1>{stepTitles[step]}</h1>
          <p className="onboarding-intro">{stepIntros[step]}</p>

          {step === 0 ? (
            <div className="onboarding-sources">
              {sources.map((source) => {
                const connected = connectedSources.has(source.id);
                return (
                  <button
                    key={source.id}
                    type="button"
                    className={`onboarding-source${connected ? ' is-connected' : ''}`}
                    onClick={() => toggleSource(source.id)}
                  >
                    <span className="onboarding-source__icon">{source.icon}</span>
                    <span className="onboarding-source__body">
                      <span className="onboarding-source__name">{source.name}</span>
                      <span className="onboarding-source__description">{source.description}</span>
                    </span>
                    <span className="onboarding-source__status">{connected ? 'conectado' : 'conectar'}</span>
                  </button>
                );
              })}
            </div>
          ) : null}

          {step === 1 ? (
            <div className="onboarding-scopes">
              {scopes.map((scope) => {
                const selected = scope.id === selectedScope;
                return (
                  <button
                    key={scope.id}
                    type="button"
                    className={`onboarding-scope${selected ? ' is-selected' : ''}`}
                    onClick={() => setSelectedScope(scope.id)}
                  >
                    <span className="onboarding-scope__mark">{selected ? '✓' : ''}</span>
                    <span className="onboarding-scope__body">
                      <span className="onboarding-scope__name">{scope.name}</span>
                      <span className="onboarding-scope__modules">{scope.modules} módulos</span>
                    </span>
                    <span className="onboarding-scope__meta">
                      <span className="onboarding-scope__eta">{scope.eta}</span>
                      <span className="onboarding-scope__repo">{scope.repo}</span>
                    </span>
                  </button>
                );
              })}
            </div>
          ) : null}

          {step === 2 ? (
            <div className="onboarding-owners">
              <div className="onboarding-owners__head">
                <span>Domínio</span>
                <span>Responsável</span>
                <span>Papel</span>
              </div>
              {owners.map((owner) => (
                <div className="onboarding-owners__row" key={owner.email}>
                  <span className="onboarding-owners__domain">{owner.domain}</span>
                  <span className="onboarding-owners__person">
                    <span className="onboarding-owners__avatar">{owner.initials}</span>
                    <span>{owner.email}</span>
                  </span>
                  <span className="onboarding-owners__role">{owner.role}</span>
                </div>
              ))}
              <button type="button" className="onboarding-owners__invite">+ Convidar outra pessoa</button>
            </div>
          ) : null}

          {step === 3 ? (
            <div className="onboarding-indexing">
              <div className="onboarding-indexing__card">
                <div className="onboarding-indexing__head">
                  <span>Indexação do ERP Financeiro</span>
                  <span className="onboarding-indexing__pct">58%</span>
                </div>
                <ProgressBar value={58} label="Progresso da indexação do ERP Financeiro" />
                <div className="onboarding-indexing__jobs">
                  {indexJobs.map((job) => (
                    <div className={`onboarding-indexing__job is-${job.status}`} key={job.id}>
                      <span className="onboarding-indexing__mark">{job.status === 'done' ? '✓' : job.status === 'active' ? '●' : '○'}</span>
                      <span className="onboarding-indexing__label">{job.label}</span>
                      <span className="onboarding-indexing__meta">{job.meta}</span>
                    </div>
                  ))}
                </div>
              </div>
              <div className="onboarding-next-steps">
                {nextSteps.map((item) => (
                  <button type="button" className="onboarding-next-step" key={item.id}>
                    <span className="onboarding-next-step__icon">{item.icon}</span>
                    <span className="onboarding-next-step__title">{item.title}</span>
                    <span className="onboarding-next-step__description">{item.description}</span>
                  </button>
                ))}
              </div>
            </div>
          ) : null}
        </div>
      </div>

      <footer className="onboarding-footer">
        <span className="onboarding-footer__hint">{stepHints[step]}</span>
        <Button variant="secondary" onClick={handleBack} disabled={step === 0}>Voltar</Button>
        <Button variant="primary" onClick={handleNext}>{ctaLabels[step]}</Button>
      </footer>
    </div>
  );
}
