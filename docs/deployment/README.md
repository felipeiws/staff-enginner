# Implantação e ambientes

Docker Compose é o ambiente local de referência porque torna dependências reproduzíveis sem impor um orquestrador de produção cedo demais. A topologia alvo reúne backend Laravel, worker Python, PostgreSQL, Redis, Typesense e object storage compatível com S3.

Produção deve separar dados persistentes de workloads stateless, usar secretos externos, TLS, backups testados, migrações compatíveis, health checks, limites de recursos e observabilidade. Compose não define a estratégia de produção; Kubernetes, serviços gerenciados ou VMs devem ser escolhidos por requisitos operacionais documentados.

Imagens devem ser reproduzíveis, não executar como root e ter versões fixadas. Reversão depende de compatibilidade de schema e de reconstrução de projeções; por isso, mudanças destrutivas exigem plano explícito.
