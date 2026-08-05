# Como escrever um analisador de código

Este guia descreve como contribuir um plugin `code.analyzer` para Angular, Vue, Node, Python, Java ou .NET. Use [`atlas.laravel`](../../plugins/atlas/laravel/README.md) e [`atlas.react`](../../plugins/atlas/react/README.md) como referências executáveis. O plugin é um produtor de observações candidatas: ele não acessa o banco, índice, segredos de outro plugin nem publica conhecimento.

## 1. Delimite a análise

Declare a stack, versões compatíveis, extensões aceitas, máximo de arquivos, bytes e tempo. A análise deve receber apenas um snapshot já observado (`payload.files` com `path` e `content_base64`) e ser determinística: não execute build, testes, código do repositório, imports, comandos de pacote ou rede. Recuse payloads fora dos limites antes de parsear.

Escolha apenas construções que podem ser ancoradas com segurança. Por exemplo: Angular pode começar em componentes, rotas e `HttpClient`; Vue em SFCs, router e clientes HTTP; Node em rotas, handlers e filas; Python em endpoints, services e tasks; Java em controllers, services e repositories; .NET em controllers, handlers e `DbContext`. Ausência de evidência não é ausência de comportamento.

## 2. Crie o pacote e manifesto

Crie `plugins/<publisher>/<nome>/` com `plugin.json`, `README.md`, `package.json`, testes e fixtures. Valide o manifesto em [`plugin-manifest.json`](../../packages/contracts/schemas/v1/plugin-manifest.json):

```json
{
  "id": "community.angular",
  "runtime": "python_worker",
  "capabilities": ["code.analyzer"],
  "permissions": ["artifact.read"],
  "configurations": {
    "framework": "angular",
    "compatible_framework_versions": ["^17.0.0"],
    "max_repository_files": 5000,
    "max_repository_bytes": 20971520,
    "max_analysis_seconds": 120,
    "static_only": true
  }
}
```

Complete também compatibilidade da plataforma, schemas de entrada/saída, `limits` (CPU, memória, concorrência, bytes e `timeout_seconds`), retry, idempotência e métricas. Não peça `network.egress` para análise estática; use somente `artifact.read`. A política de runtime, segredos e egress está em [Segurança de plugins](security.md).

## 3. Produza somente o contrato de saída

Use [`code-analysis-result.json`](../../packages/contracts/schemas/v1/code-analysis-result.json). Todo candidato e assertion deve permanecer em `candidate`, incluir `provenance` (observação, revisão, método/versão e data), confiança e uma evidência de artefato. A evidência contém `pointer` no formato `arquivo:linha`, offsets em linha, checksum do trecho e o commit em `evidence.commit`.

Use os predicados do contrato (`calls`, `reads`, `writes`, `produces`, `consumes`, `implements` e `exposes`) somente quando a construção estática justificar a relação. Referências ainda não resolvidas, como uma chamada HTTP, devem ser marcadas como externas em vez de inventar uma entidade interna. Saídas de inferência continuam candidatas e seguem governança humana; o analisador não pode aprová-las.

## 4. Isole e torne reprocessável

O método e `execution.analyzer_version` devem identificar a versão exata, por exemplo `community.angular@1.0.0`; `execution.kind` é `static_analysis` e `reprocessable` é `true`. Assim, a plataforma consegue reexecutar uma revisão quando o analisador evolui, sem reescrever o histórico. Propague `task.idempotency_key`, `correlation_id`, escopo e deadline pelo host; trate reentregas como seguras.

## 5. Teste com um repositório pequeno

Inclua fixture representativa da stack com pelo menos uma entidade de cada tipo suportado e relações positivas e negativas. Teste: limites de arquivo/bytes, rejeição de Base64 inválido, ausência de I/O/execução, candidatos, assertions, `candidate`, revisão, método, confiança, `arquivo:linha` e commit. Execute testes e lint do pacote, a suíte do runtime e a validação de schemas antes de publicar.

## Publicação e rollback

Versione o pacote e o manifesto juntos. A instalação administrativa valida compatibilidade e permissões, e o host executa o plugin isolado. Meça duração, arquivos analisados, candidatos, assertions e falhas por `plugin_id` e correlação. Para rollback, desabilite o plugin: candidatos e execuções já auditados são preservados e nenhuma alteração automática é feita no grafo.
