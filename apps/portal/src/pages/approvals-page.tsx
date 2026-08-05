import { useEffect, useRef, useState } from 'react';
import { PortalHeader, PortalSidebar } from '@/components';
import {
  useApproval,
  useApprovals,
  useApproveApproval,
  useEditApprovalText,
  useRejectApproval,
  useRequestApprovalAiRevision,
} from '@/data/hooks';
import { DataError, DataSkeleton } from '@/data/query-state';
import type { ApprovalSuccess } from '@/data/types';
import './approvals-page.css';

const color = (value?: string) => `var(--${value ?? 'acc'})`;
const lines = (value: string) => value.split(/\n+/).filter(Boolean);

export function ApprovalsPage() {
  const approvals = useApprovals();
  const [selectedId, setSelectedId] = useState('');
  const [justification, setJustification] = useState('');
  const [validation, setValidation] = useState('');
  const [success, setSuccess] = useState<ApprovalSuccess>();
  const proposalEditor = useRef<HTMLTextAreaElement>(null);
  const selected = selectedId || approvals.data?.approvals[0]?.id || '';
  const detail = useApproval(selected);
  const approve = useApproveApproval();
  const edit = useEditApprovalText();
  const requestAi = useRequestApprovalAiRevision();
  const reject = useRejectApproval();
  const [proposedText, setProposedText] = useState('');

  useEffect(() => { setSuccess(undefined); setJustification(''); setValidation(''); }, [selected]);
  useEffect(() => { if (detail.data) setProposedText(detail.data.diff.proposed); }, [detail.data]);
  useEffect(() => {
    const shortcut = (event: globalThis.KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
        event.preventDefault();
        document.getElementById('approval-approve')?.click();
      }
    };
    window.addEventListener('keydown', shortcut);
    return () => window.removeEventListener('keydown', shortcut);
  }, []);

  const requireJustification = () => {
    if (justification.trim()) return true;
    setValidation('Informe a justificativa antes de decidir; esse texto será incluído no commit.');
    return false;
  };
  const handleApprove = async () => {
    if (!selected || !requireJustification()) return;
    try { setSuccess(await approve.mutateAsync({ id: selected, value: justification.trim() })); }
    catch { setValidation('Não foi possível abrir o Pull Request. A proposta continua pendente.'); }
  };
  const handleEdit = async () => {
    if (!selected || !proposedText.trim()) return;
    try { await edit.mutateAsync({ id: selected, value: proposedText.trim() }); proposalEditor.current?.focus(); }
    catch { setValidation('Não foi possível salvar a edição da proposta.'); }
  };
  const handleAi = async () => {
    if (!selected) return;
    try { await requestAi.mutateAsync({ id: selected, value: 'Revise o texto proposto, preserve as evidências e explicite o retorno 409.' }); setValidation('Ajuste solicitado à IA; a proposta permanece candidata até nova revisão humana.'); }
    catch { setValidation('Não foi possível pedir o ajuste à IA.'); }
  };
  const handleReject = async () => {
    if (!selected || !requireJustification()) return;
    try { await reject.mutateAsync({ id: selected, value: justification.trim() }); setSelectedId(''); }
    catch { setValidation('Não foi possível rejeitar a proposta.'); }
  };
  const handleSelection = (id: string) => { setSelectedId(id); };

  return <div className="portal-shell"><PortalSidebar /><main id="conteudo-principal" className="portal-content"><PortalHeader />
    <div className="approvals-page">
      <h1>Aprovações</h1>
      <p className="approvals-page__intro">Mudanças de código detectadas pelo CI geram propostas de conhecimento. Revise, ajuste e aprove — o portal abre o Pull Request.</p>
      {approvals.isLoading ? <DataSkeleton lines={8} /> : approvals.error ? <DataError error={approvals.error} /> : <div className="approvals-grid">
        <aside className="approvals-list" aria-label="Propostas pendentes">
          {approvals.data?.approvals.map((approval) => <button key={approval.id} type="button" onClick={() => handleSelection(approval.id)} className={`approvals-list__item${approval.id === selected ? ' is-selected' : ''}`}>
            <i style={{ background: color(approval.entity_type_color) }} /><span><strong>{approval.title}</strong><small>{approval.author?.id ?? approval.origin?.detected_by ?? 'Origem indisponível'} · {approval.origin?.revision ? `commit ${approval.origin.revision}` : approval.age ?? ''}</small><small>{approval.age}</small></span>
          </button>)}
          <footer>+{approvals.data?.low_impact_auto_approval?.count ?? 0} propostas de baixo impacto · aprovação automática em {approvals.data?.low_impact_auto_approval?.hours ?? 48}h</footer>
        </aside>
        <section className="approval-panel" aria-label="Detalhe da proposta">
          {detail.isLoading ? <DataSkeleton lines={7} /> : detail.error ? <DataError error={detail.error} /> : detail.data ? success ? <ApprovalSuccessView success={success} entities={detail.data.impact.entities} onNext={() => { setSuccess(undefined); setSelectedId(approvals.data?.approvals.find((item) => item.id !== selected)?.id ?? ''); }} /> : <>
            <header className="approval-panel__header"><span className={`atlas-entity-badge atlas-entity-badge--${detail.data.entity_type_color ?? 'acc'}`}>{detail.data.entity_type ?? 'Conhecimento'}</span><h2>{detail.data.title}</h2><small>origem: commit {detail.data.origin?.revision ?? detail.data.provenance.revision} · {detail.data.origin?.detected_by ?? detail.data.author?.id}</small></header>
            <div className="approval-analysis"><span>✦</span><div><strong>Análise da IA · confiança {detail.data.analysis.confidence.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</strong><p>{detail.data.analysis.text}</p></div></div>
            <div className="approval-diff"><section><label>atual</label>{lines(detail.data.diff.current).map((line, index) => <code key={index}>{line}</code>)}</section><section><label>proposto · editável</label><textarea ref={proposalEditor} aria-label="Texto proposto editável" value={proposedText} onChange={(event) => setProposedText(event.target.value)} /></section></div>
            <div className="approval-actions"><label htmlFor="approval-justification">Justificativa (vai para o commit)</label><textarea id="approval-justification" value={justification} onChange={(event) => { setJustification(event.target.value); setValidation(''); }} placeholder="Ex.: alinhado com o time Fiscal em 30/07; auditoria pediu menção explícita ao 409." />{validation ? <p role="alert">{validation}</p> : null}<div><button id="approval-approve" type="button" className="atlas-button atlas-button--primary" onClick={() => void handleApprove()} disabled={approve.isPending}>Aprovar e abrir PR</button><button type="button" onClick={() => void handleEdit()} disabled={edit.isPending}>Editar texto</button><button type="button" onClick={() => void handleAi()} disabled={requestAi.isPending}>Pedir ajuste à IA</button><button type="button" onClick={() => void handleReject()} disabled={reject.isPending}>Rejeitar</button><span /><small>1 revisor · atalho ⌘↵</small></div></div>
          </> : <p className="approval-panel__empty">Não há propostas visíveis para revisão.</p>}
        </section>
      </div>}
    </div>
  </main></div>;
}

function ApprovalSuccessView({ success, entities, onNext }: { success: ApprovalSuccess; entities: readonly string[]; onNext: () => void }) {
  return <div className="approval-success"><div><i>✓</i><strong>Aprovado · {success.pull_request.reference} aberto em erp-financeiro/knowledge</strong></div><p>Commit <code>{success.commit}</code> · reindexação em {success.reindexing.target} · {entities.join(' e ') || 'Entidades dependentes'} marcada{entities.length === 1 ? '' : 's'} para revisão automática.</p><button type="button" onClick={onNext}>Revisar a próxima proposta</button></div>;
}
