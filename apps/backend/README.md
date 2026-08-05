# Backend Atlas

Backend Laravel 13 do monólito modular definido no [ADR-001](../../docs/adr/001-laravel-backend.md).

## Estrutura

Cada módulo em `src/Modules` mantém as camadas `Domain`, `Application`,
`Infrastructure` e `Http`. Dependências entre módulos não podem acessar
implementações de outro módulo; a integração futura deve ocorrer por contratos
de aplicação ou pelos contratos versionados em `atlas/contracts`.

Os módulos inicializados são `KnowledgeEngine`, `Search`, `Governance`,
`Ingestion`, `Copilot` e `Plugins`. Cada um possui seu próprio provider.

## Serviços locais

Copie `.env.example` para `.env`. A configuração padrão usa PostgreSQL,
Redis e MinIO locais definidos em `../../docker/docker-compose.yml`. O disco
S3 usa MinIO com endpoint path-style em `http://127.0.0.1:9000` e bucket
`atlas` (crie-o antes de usar armazenamento de objetos).

`GET /health` verifica a conectividade com banco, Redis e o disco S3. Retorna
`200` quando todos estão disponíveis e `503` com os checks individuais quando
algum deles falha.

## Domínio demonstrativo

Após aplicar as migrations, execute `php artisan atlas:seed-demo` para carregar
o domínio fictício Nordika do protótipo. O comando é idempotente e cria cada
item por observação versionada, ciclo de vida e assertions com evidência.

## Qualidade

```bash
composer test
composer analyse
composer lint
```

O PHPStan usa nível 8. Os testes de arquitetura impedem acesso entre módulos e
impedem a camada `Http` de usar Eloquent diretamente.
