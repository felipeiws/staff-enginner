# Contribuindo

Obrigado por ajudar a construir uma plataforma que trata conhecimento de software como ativo verificável. Contribuições de documentação, arquitetura, conectores e produto são bem-vindas.

## Antes de começar

Leia `README.md`, `AGENTS.md`, o documento do módulo e ADRs afetados. Discuta mudanças que alterem contratos, dados, segurança, custos ou compatibilidade antes de abrir uma implementação. O repositório favorece decisões pequenas, reversíveis e documentadas.

## Fluxo de contribuição

1. Crie uma branch curta a partir de `main`: `docs/`, `feat/`, `fix/`, `chore/` ou `plugin/<nome>/`.
2. Faça uma mudança coerente e inclua documentação, testes e observabilidade compatíveis com o escopo.
3. Use Conventional Commits (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`); escopos são opcionais.
4. Abra PR com problema, decisão, evidências, riscos, compatibilidade, validação e rollback quando houver alteração operacional.

Commits devem ser pequenos e não misturar refatoração incidental. Nunca inclua credenciais, dados de produção ou artefatos gerados sem necessidade.

## Revisão

O autor é responsável pela clareza e por responder comentários. Revisores analisam correção, simplicidade, segurança, compatibilidade, operação e qualidade documental. Alterações em contratos, ADRs, segurança ou plugins requerem pelo menos uma revisão de mantenedor. Mantenedores podem pedir um Design Document para decisões de grande alcance.

## Documentação e ADRs

Explique o porquê, alternativas descartadas, consequências e como reverter. Crie ADR para decisões duradouras ou transversais. Não edite um ADR aceito para reescrever história; crie um que o superseda.

## Plugins

Siga `docs/plugin-system/README.md`. Um plugin deve ser isolado, versionado, idempotente, observável e declarar permissões/capacidades. Não acesse bancos internos nem execute ações externas sem autorização explícita e auditável.

## Conduta e segurança

Siga o [Código de Conduta](CODE_OF_CONDUCT.md). Vulnerabilidades não devem ser reportadas em issues públicas; use o processo em [SECURITY.md](SECURITY.md).
