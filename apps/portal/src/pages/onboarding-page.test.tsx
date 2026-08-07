import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { describe, expect, it } from 'vitest';
import { OnboardingPage } from './onboarding-page';

function LocationProbe() { const location = useLocation(); return <output>{location.pathname}</output>; }
function renderPage() {
  return render(
    <MemoryRouter initialEntries={['/onboarding']}>
      <OnboardingPage />
      <LocationProbe />
    </MemoryRouter>,
  );
}

describe('OnboardingPage', () => {
  it('renderiza o passo 1 com as fontes de verdade', () => {
    renderPage();
    expect(screen.getByText('Passo 1 de 4')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Conecte as fontes de verdade' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Repositório Git/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Contrato de API \(OpenAPI\)/ })).toBeInTheDocument();
  });

  it('avança e volta entre os passos pelo CTA e pelo botão Voltar', () => {
    renderPage();
    const backButton = screen.getByRole('button', { name: 'Voltar' });
    expect(backButton).toBeDisabled();

    fireEvent.click(screen.getByRole('button', { name: 'Continuar' }));
    expect(screen.getByRole('heading', { name: 'Escolha o escopo inicial' })).toBeInTheDocument();
    expect(backButton).not.toBeDisabled();

    fireEvent.click(screen.getByRole('button', { name: 'Continuar' }));
    expect(screen.getByRole('heading', { name: 'Defina quem responde por cada domínio' })).toBeInTheDocument();

    fireEvent.click(backButton);
    expect(screen.getByRole('heading', { name: 'Escolha o escopo inicial' })).toBeInTheDocument();
  });

  it('pula direto para qualquer passo pelas pastilhas do header', () => {
    renderPage();
    fireEvent.click(screen.getByRole('button', { name: /Indexação/ }));
    expect(screen.getByRole('heading', { name: 'A indexação já começou' })).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: /Fontes/ }));
    expect(screen.getByRole('heading', { name: 'Conecte as fontes de verdade' })).toBeInTheDocument();
  });

  it('marca uma fonte como conectada ao clicar no card', () => {
    renderPage();
    const source = screen.getByRole('button', { name: /Repositório Git/ });
    expect(source).toHaveClass('onboarding-source');
    expect(source).not.toHaveClass('is-connected');
    fireEvent.click(source);
    expect(source).toHaveClass('is-connected');
    expect(screen.getByRole('button', { name: /Repositório Git.*conectado/s })).toBeInTheDocument();
    fireEvent.click(source);
    expect(source).not.toHaveClass('is-connected');
  });

  it('seleciona um único escopo inicial no passo 2', () => {
    renderPage();
    fireEvent.click(screen.getByRole('button', { name: 'Continuar' }));
    const erp = screen.getByRole('button', { name: /ERP Financeiro/ });
    const crm = screen.getByRole('button', { name: /CRM de Vendas/ });
    fireEvent.click(erp);
    expect(erp).toHaveClass('is-selected');
    fireEvent.click(crm);
    expect(crm).toHaveClass('is-selected');
    expect(erp).not.toHaveClass('is-selected');
  });

  it('mostra o passo 4 com indexação e chama navigate("/") pelo CTA final', () => {
    renderPage();
    fireEvent.click(screen.getByRole('button', { name: /Indexação/ }));
    expect(screen.getByRole('heading', { name: 'A indexação já começou' })).toBeInTheDocument();
    expect(screen.getByRole('progressbar', { name: /Progresso da indexação/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Pergunte ao Copiloto/ })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Concluir' }));
    expect(screen.getByText('/')).toBeInTheDocument();
  });

  it('navega para "/" ao clicar em pular configuração', () => {
    renderPage();
    fireEvent.click(screen.getByRole('button', { name: 'pular configuração →' }));
    expect(screen.getByText('/')).toBeInTheDocument();
  });
});
