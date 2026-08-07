import { useEffect, useState, type FormEvent } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Button } from '@/components';
import { useCurrentPrincipal, useLogin } from '@/data/hooks';
import { LoginValidationError } from '@/data/session-api';
import './login-page.css';

const loginStats = [
  { value: '148 mil', label: 'entidades indexadas' },
  { value: '4', label: 'aplicações conectadas' },
  { value: '92%', label: 'respostas com fonte' },
];

export function LoginPage() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const nextPath = searchParams.get('next') ?? '/';
  const currentPrincipal = useCurrentPrincipal();
  const login = useLogin();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (currentPrincipal.data) void navigate(nextPath, { replace: true });
  }, [currentPrincipal.data, navigate, nextPath]);

  const submit = (event: FormEvent) => {
    event.preventDefault();
    setErrorMessage(null);
    login.mutate(
      { email, password },
      {
        onSuccess: () => void navigate(nextPath, { replace: true }),
        onError: (error) => {
          if (error instanceof LoginValidationError) {
            setErrorMessage(error.message || error.fieldErrors.email?.[0] || 'Credenciais inválidas.');
          } else {
            setErrorMessage('Não foi possível entrar. Tente novamente em instantes.');
          }
        },
      },
    );
  };

  return (
    <div className="login-page">
      <div className="login-brand">
        <div className="login-brand__logo">
          <span className="login-brand__mark">A</span>
          <span className="login-brand__name">Atlas</span>
          <span className="login-brand__tag">knowledge platform</span>
        </div>
        <div className="login-brand__pitch">
          <h1>O conhecimento das suas aplicações, sempre com fonte.</h1>
          <p>
            Processos, regras de negócio, telas e APIs indexados do código real. O copiloto só responde
            com evidência rastreável.
          </p>
          <div className="login-brand__stats">
            {loginStats.map((stat) => (
              <div key={stat.label}>
                <div className="login-brand__stat-value">{stat.value}</div>
                <div className="login-brand__stat-label">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>
        <div className="login-brand__status">
          <span aria-hidden="true" className="login-brand__pulse" />
          <span>indexadores ativos · última sincronização há 4 min</span>
        </div>
      </div>
      <div className="login-form-wrap">
        <form className="login-form" onSubmit={submit}>
          <span className="login-form__label">Entrar</span>
          <h2>Acesse sua organização</h2>
          <p>
            Entre com suas credenciais corporativas. Seu acesso ao conhecimento respeita as permissões
            dos sistemas de origem.
          </p>
          {errorMessage ? (
            <p className="login-form__alert" role="alert">
              {errorMessage}
            </p>
          ) : null}
          <label className="login-form__field">
            <span>E-mail</span>
            <input
              onChange={(event) => setEmail(event.target.value)}
              placeholder="voce@nordika.com.br"
              type="email"
              value={email}
            />
          </label>
          <label className="login-form__field">
            <span>Senha</span>
            <input
              onChange={(event) => setPassword(event.target.value)}
              placeholder="••••••••••"
              type="password"
              value={password}
            />
          </label>
          <div className="login-form__row">
            <label className="login-form__remember">
              <input
                checked={remember}
                onChange={(event) => setRemember(event.target.checked)}
                type="checkbox"
              />
              Manter conectado
            </label>
            <a href="#">Esqueci a senha</a>
          </div>
          <Button className="login-form__submit" disabled={login.isPending} type="submit">
            {login.isPending ? 'Entrando…' : 'Entrar'}
          </Button>
          <p className="login-form__onboarding">
            Primeiro acesso da sua empresa?{' '}
            <button onClick={() => void navigate('/onboarding')} type="button">
              Configurar o workspace
            </button>
          </p>
        </form>
      </div>
    </div>
  );
}
