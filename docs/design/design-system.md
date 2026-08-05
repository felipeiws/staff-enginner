# Sistema de design do Portal Atlas

O [protótipo normativo](prototype/README.md) é a fonte visual para o Portal Atlas. Este
documento registra os tokens extraídos dele para que novas telas preservem a mesma
hierarquia visual, nos temas escuro e claro.

## Implementação

Os tokens globais vivem em
[`apps/portal/src/styles.css`](../../apps/portal/src/styles.css). O tema escuro é o
padrão em `:root`; o claro é ativado em `:root[data-theme='light']`. Não se devem
introduzir valores de cor paralelos nos componentes: use as propriedades abaixo.

| Token        | Escuro                       | Claro                         | Uso                         |
| ------------ | ---------------------------- | ----------------------------- | --------------------------- |
| `--bg`       | `#08090B`                    | `#FAFAF9`                     | Fundo da aplicação          |
| `--surface`  | `#0F1114`                    | `#FFFFFF`                     | Superfícies e cards         |
| `--surface2` | `#14171B`                    | `#F4F5F6`                     | Superfícies secundárias     |
| `--sel`      | `#1B1F24`                    | `#ECEEF0`                     | Seleção e destaque discreto |
| `--line`     | `rgba(255,255,255,.09)`      | `rgba(0,0,0,.11)`             | Borda principal             |
| `--line2`    | `rgba(255,255,255,.05)`      | `rgba(0,0,0,.06)`             | Divisor sutil               |
| `--tx`       | `#E8EAED`                    | `#14171A`                     | Texto principal             |
| `--tx2`      | `#98A0AA`                    | `#5B646E`                     | Texto secundário            |
| `--tx3`      | `#666E78`                    | `#8B949E`                     | Metadados e texto terciário |
| `--acc`      | `#4EE1A0`                    | `#0FA36B`                     | Ação e estado positivo      |
| `--accSoft`  | `rgba(78,225,160,.11)`       | `rgba(15,163,107,.09)`        | Fundo positivo suave        |
| `--warn`     | `#F5B547`                    | `#B26A00`                     | Atenção                     |
| `--danger`   | `#FF6B6B`                    | `#D6323F`                     | Erro ou risco               |
| `--info`     | `#6AA8FF`                    | `#2563EB`                     | Informação                  |
| `--violet`   | `#A78BFA`                    | `#7C4DE0`                     | Processo                    |
| `--pink`     | `#F472B6`                    | `#DB2777`                     | Evento                      |
| `--sky`      | `#38BDF8`                    | `#0284C7`                     | Destaque complementar       |
| `--shadow`   | `0 12px 40px rgba(0,0,0,.5)` | `0 12px 32px rgba(0,0,0,.12)` | Elevação                    |

## Tipografia

As famílias são distribuídas como WOFF2 locais em
`apps/portal/src/assets/fonts`; por isso o Portal não depende do Google Fonts em
runtime. Os assets cobrem os subconjuntos latino e latino estendido, incluindo o
português do Brasil.

| Papel                                         | Família e peso        | Escala observada                                               |
| --------------------------------------------- | --------------------- | -------------------------------------------------------------- |
| Títulos e números de destaque                 | Space Grotesk 400–700 | 32, 27, 25 e 22px; títulos em 600 com `letter-spacing: -.02em` |
| Texto corrido                                 | IBM Plex Sans 400–600 | 12,8–13,5px; entradas maiores podem usar 14–15px               |
| Metadados, identificadores e rótulos técnicos | IBM Plex Mono 400–500 | 10–11px; rótulos em maiúsculas com `letter-spacing: .09em`     |

## Movimento e acabamento global

O stylesheet preserva as animações `fadeUp` (entrada de 6px), `blink` (indicadores
de atividade) e `dash` (traçados SVG), além da scrollbar de 9px com thumb em
`--line`. O corpo usa `text-wrap: pretty`; `input`, `textarea` e `button` herdam a
família tipográfica atual.

## Primitivos reutilizáveis

Os primitivos do Portal estão em
[`apps/portal/src/components`](../../apps/portal/src/components). Eles reproduzem
os padrões normativos do protótipo: `Card`, `SectionLabel`, `EntityBadge`,
`FreshnessSeal`, `ConfidencePill`, `Button`, `SearchInput`, as duas variantes de
`Tabs`, `ProgressBar`, `Sparkline`, `MetaRow`, `ChipList`, `DiffPane`,
`EmptyState` e `SkeletonLoader`. Todos usam somente os tokens acima, inclusive
nos dois temas. As histórias em Storybook incluem uma variação clara e outra
escura de cada primitivo; os testes verificam nomes acessíveis, papéis e estados
de controles.
