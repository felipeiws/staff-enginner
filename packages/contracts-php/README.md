# atlas/contracts

Pacote PHP 8.3, sem dependência de Laravel ou banco de dados, para consumir os
contratos v1 da Atlas. Os DTOs em `src/Dto` são `readonly`; a
`ContractFactory` valida a entrada contra os JSON Schemas com
`opis/json-schema` antes de desserializá-la.

```php
use Atlas\Contracts\Factory\ContractFactory;

$entity = (new ContractFactory())->knowledgeEntity($payload);
```

Uma instância inválida lança `ContractViolation`, contendo o contrato e o
detalhe da violação. `GraphPredicate` e `KnowledgeLifecycleState` são enums
nativos e têm paridade testada contra os schemas canônicos de
`../contracts/schemas/v1`.

Os schemas em `resources/schemas/v1` acompanham o pacote instalado para que a
validação não dependa do monorepo. Ao alterar um schema canônico, atualize essa
cópia e execute:

```bash
composer install
composer test
composer analyse
```
