import { useEffect, useState, type FormEvent, type KeyboardEvent } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { PortalHeader, PortalSidebar } from '@/components';
import { useCopilotAsk } from '@/data/hooks';
import type { CopilotAction, CopilotAnswer, CopilotEntity } from '@/data/types';
import './copilot-page.css';

const organization = 'nordika';
const suggestions = [
  'Como funciona o cancelamento de pedido de compra?',
  'Quais regras impedem o faturamento?',
  'Onde está a integração de estoque?',
  'Quais lacunas existem no ERP Financeiro?',
];
type ChatMessage = { kind: 'user'; text: string } | { kind: 'answer'; answer: CopilotAnswer };

const routeForEntity = (entity: CopilotEntity) => {
  const section = ({ Processo: 'processos', Regra: 'regras', Tela: 'telas', API: 'apis', Endpoint: 'apis' } as Record<string, string>)[entity.type];
  return section ? `/${section}/${encodeURIComponent(entity.id)}` : `/graph?entity=${encodeURIComponent(entity.id)}`;
};

export function CopilotPage() {
  const [params] = useSearchParams();
  const [input, setInput] = useState(params.get('q') ?? '');
  const [messages, setMessages] = useState<readonly ChatMessage[]>([]);
  const [reasoningOpen, setReasoningOpen] = useState(false);
  const ask = useCopilotAsk();

  useEffect(() => setInput(params.get('q') ?? ''), [params]);
  const send = (question = input) => {
    const value = question.trim();
    if (!value || ask.isPending) return;
    setMessages((current) => [...current, { kind: 'user', text: value }]);
    setInput('');
    ask.mutate(
      { input: { question: value, organization } },
      { onSuccess: (answer) => setMessages((current) => [...current, { kind: 'answer', answer }]) },
    );
  };
  const submit = (event: FormEvent<HTMLFormElement>) => { event.preventDefault(); send(); };
  const onComposerKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') { event.preventDefault(); send(); }
  };

  return (
    <div className="portal-shell">
      <PortalSidebar />
      <main id="conteudo-principal" className="portal-content copilot-shell">
        <PortalHeader />
        <section className="copilot-page" aria-label="Copiloto do conhecimento">
          <div className="copilot-scroll">
            <div className="copilot-conversation" aria-live="polite">
              {!messages.length ? <EmptyState onSuggestion={send} /> : null}
              {messages.map((message, index) => message.kind === 'user'
                ? <div className="copilot-user-message" key={`${message.text}-${index}`}>{message.text}</div>
                : <Answer key={`${message.answer.opening}-${index}`} answer={message.answer} reasoningOpen={reasoningOpen} onReasoning={() => setReasoningOpen((open) => !open)} />)}
              {ask.isPending ? <div className="copilot-thinking" role="status"><span>✦</span><span>recuperando evidências…</span></div> : null}
              {ask.isError ? <p className="copilot-error" role="alert">{ask.error.message}</p> : null}
            </div>
          </div>
          <form className="copilot-composer-wrap" onSubmit={submit}>
            <div className="copilot-composer">
              <span className="copilot-scope">escopo: ERP Financeiro</span>
              <input aria-label="Pergunte ao Copiloto" value={input} onChange={(event) => setInput(event.target.value)} onKeyDown={onComposerKeyDown} placeholder="Pergunte com suas palavras…" />
              <button aria-label="Enviar pergunta" disabled={!input.trim() || ask.isPending} type="submit">↵</button>
            </div>
            <p>Sem evidência suficiente, o Copiloto não responde — a pergunta vira uma lacuna rastreada em Saúde do conhecimento.</p>
          </form>
        </section>
      </main>
    </div>
  );
}

function EmptyState({ onSuggestion }: { onSuggestion: (question: string) => void }) {
  return <div className="copilot-empty">
    <h1>Copiloto do conhecimento</h1>
    <p>Responde apenas com base no que está indexado. Sem evidência suficiente, ele diz que não sabe e registra a lacuna no dashboard.</p>
    <div>{suggestions.map((suggestion) => <button key={suggestion} onClick={() => onSuggestion(suggestion)} type="button">{suggestion}</button>)}</div>
  </div>;
}

function Answer({ answer, reasoningOpen, onReasoning }: { answer: CopilotAnswer; reasoningOpen: boolean; onReasoning: () => void }) {
  const navigate = useNavigate();
  const primary = answer.entities.find((entity) => entity.type === 'Processo') ?? answer.entities[0];
  const action = (item: CopilotAction) => {
    if (item.id === 'deepen_process' && primary) return void navigate(routeForEntity(primary));
    if (item.id === 'view_graph') return void navigate(`/graph${primary ? `?entity=${encodeURIComponent(primary.id)}` : ''}`);
    if (item.id === 'create_faq') return void navigate('/aprovacoes');
    return void navigate('/saude');
  };
  return <article className="copilot-answer">
    <header><span>✦</span><b>Resposta</b><em>confiança {answer.confidence}% · {answer.source_count} {answer.source_count === 1 ? 'fonte' : 'fontes'}</em></header>
    <p className="copilot-answer-opening">{answer.opening}</p>
    <div className="copilot-answer-blocks">{answer.blocks.map((block) => <p key={block.citation.revision_id}>{block.text}</p>)}</div>
    <section className="copilot-sources" aria-label="Fontes utilizadas">
      <h2>Fontes utilizadas</h2>
      {answer.sources.map((source) => <a href={source.provenance.uri} key={`${source.provenance.revision_id}-${source.name}`} rel="noreferrer" target="_blank"><span className={`copilot-type copilot-type--${source.type.color}`}>{source.type.name}</span><strong>{source.name}</strong><small>{source.freshness}</small><i>{source.relevance}%</i></a>)}
      <button className="copilot-reasoning" onClick={onReasoning} type="button">{reasoningOpen ? '▾' : '▸'} {reasoningOpen ? 'Ocultar linha de raciocínio' : 'Ver linha de raciocínio'}</button>
      {reasoningOpen ? <div className="copilot-reasoning-steps">{answer.reasoning.map((step) => <p key={step}>{step}</p>)}</div> : null}
    </section>
    <div className="copilot-entities">{answer.entities.map((entity) => <Link className={`copilot-entity copilot-entity--${entity.color}`} key={entity.id} to={routeForEntity(entity)}>{entity.name}</Link>)}</div>
    <footer>{answer.actions.map((item) => <button className={item.id === 'deepen_process' ? 'is-primary' : item.id === 'report_error' ? 'is-muted' : ''} key={item.id} onClick={() => action(item)} type="button">{item.label}</button>)}</footer>
  </article>;
}
