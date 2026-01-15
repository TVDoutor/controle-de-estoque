# Mapeamento do Sistema de Controle de Estoque

## 1. Visao geral

- **Stack**: PHP 8.1+, MySQL 5.7+/MariaDB 10.4+, PDO, Tailwind CSS via CDN, Alpine.js para interacoes (forms e toggle de tema), Chart.js em `public/dashboard.php`.
- **Estrutura principal**:
  - `public/`: rotas acessiveis pelo navegador (login, dashboard, modulos de negocio).
  - `includes/`: configuracao (`config.php`), conexao (`database.php`), sessao (`session.php`), autenticacao e helpers reutilizaveis.
  - `templates/`: layout compartilhado (header, sidebar, topbar, footer) e recursos de tema em `theme-resources.php`.
  - `scripts/`: utilitarios CLI (provisionamento de DB, criacao de admin, importacao em massa, diagnosticos).
  - `data/import/`: exemplo de CSV para carga em lote.
  - `schema.sql`: definicao completa do banco `controle_estoque`.
  - Arquivos de suporte/diagnostico (`phpinfo.php`, `public/test_db.php`, `test_db_root.php`, `tmp_dashboard.html`) devem ser removidos em producao apos testes.
- **Arquivos de entrada e roteamento**:
  - `index.php` (raiz): bootstrap para hospedar em ambientes sem DocumentRoot apontando para `public/`.
  - `public/index.php`: redireciona usuario logado para dashboard e visitantes para login.

## 2. Perfis e controle de acesso

- **Perfis** definidos em `users.role`: `admin`, `gestor`, `usuario`.
- **Autenticacao** (`includes/auth.php`):
  - `login()` valida email, senha hash Bcrypt e flag `is_active`. Atualiza `last_login`, salva `user_id`/`user_role` em sessao e regenera o ID.
  - `logout()` limpa sessao/cookie; usado por `public/logout.php`.
  - `require_login($roles)` bloqueia acesso quando nao autenticado e valida perfil exigido por pagina.
  - `user_has_role()` e `current_user()` sao usados para liberar funcoes sensiveis (ex.: exclusao de equipamento apenas para admin).
  - `ensure_csrf_token()` / `validate_csrf_token()`: todos os POST relevantes possuem anti-CSRF.
  - `enforce_session_timeout()` suporta `SESSION_IDLE_TIMEOUT` (se definido) para logout automatico por inatividade.
- **Controles por modulo** (via `require_login`):
  - Admin exclusivo: `public/usuarios.php`, `public/admin_dashboard.php`, exclusoes definitivas de equipamentos.
  - Admin + gestor: entradas (`entrada_cadastrar.php`), saidas (`saida_registrar.php`), retornos (`retornos.php`), configuracoes/modelos (`configuracoes.php`).
  - Todos os perfis autenticados: dashboard, listagens de equipamentos, clientes (somente leitura para usuario padrao), relatorios, perfil pessoal.

## 3. Modulos e funcionalidades

### 3.1 Autenticacao e sessao

- `public/login.php`: formulario com feedback via flash; impede acesso quando ja logado. Inclui toggle de tema (Alpine store `uiTheme`).
- `public/recuperar_senha.php`: coleta email e registra evento em log; sempre mostra mensagem neutra para evitar enumeracao de usuarios.
- `public/logout.php`: confirma saida e exige POST com CSRF.
- `includes/session.php`: define `SESSION_NAME`, ajusta SameSite=Lax e forca cookies `secure` quando HTTPS.

### 3.2 Dashboard operacional (`public/dashboard.php`)

- KPIs: totais de equipamentos por status (`em_estoque`, `alocado`, `manutencao`, `descartar`), total de clientes.
- Janela de 30 dias: contagem de operacoes e itens por tipo (`ENTRADA`, `SAIDA`, `RETORNO`).
- Tendencia de saidas nos ultimos 12 meses, distribuicao geografica de itens enviados (estado do cliente) e condicao dos equipamentos.
- Top modelos por movimentacao, lista de movimentacoes recentes (cliente, responsavel, quantidade) e atalho para relatorios.
- Usa Chart.js com dados embutidos em JSON.

### 3.3 Dashboard administrativo (`public/admin_dashboard.php`)

- Resumo apenas para admin: total de usuarios, distribuicao por perfil, status de equipamentos, grafico de operacoes nos ultimos 14 dias e ultimas movimentacoes.
- Disponibiliza botoes rapidos para Usuarios, Equipamentos e Relatorios.

### 3.4 Equipamentos

- **Listagem (`public/equipamentos.php`)**:
  - Filtros por status, condicao (`novo`/`usado`), termo livre (asset_tag, serie, MAC) e modelo/marca.
  - Mostra etiqueta, modelo, serie, MAC, status com badge, condicao, cliente atual (quando `current_client_id` nao nulo) e link para detalhes.
  - Botoes para iniciar fluxo de entrada e saida.
- **Detalhe (`public/equipamento_detalhe.php`)**:
  - Resumo com dados do modelo, cliente atual, status e condicao.
  - Historico de movimentacoes via `equipment_operation_items` (inclui acessorios recebidos e condicao pos-retorno).
  - Bloco de anotacoes (`equipment_notes`), permitindo texto livre por qualquer usuario autenticado.
  - Acoes condicionais:
    - Atualizar status manualmente (admin/gestor) com opcoes `em_estoque`, `alocado`, `manutencao`, `baixado`.
    - Excluir equipamento (somente admin): remove itens relacionados via transacao (operation_items, notes, equipment).
    - Adicionar anotacao (todos os perfis).
- **Cadastro de entrada (`public/entrada_cadastrar.php`)**:
  - Formulario dividido em secoes (identificacao, status inicial, metadados tecnicos, observacoes) com suporte a Alpine.js.
  - Campos chave: asset_tag, modelo, serie (obrigatorio), MAC (normalizado), condicao, data de entrada, lote, player_id, legacy_id, versoes de OS/App, flags `is_discarded` e `is_unlinked`.
  - Validacoes: modelo ativo, formato de MAC, data no formato ISO, condicao permitida.
  - Fluxo salva `equipment`, gera operacao `ENTRADA` + item vinculado e registra detalhes tecnicos/flags como anotacao para rastreabilidade.
  - Se `is_discarded` estiver marcado, equipamento ja nasce com status `baixado`.

### 3.5 Movimentacoes

- **Saida (`public/saida_registrar.php`)**:
  - Lista equipamentos `em_estoque` (com modelo e condicao) para selecao multipla.
  - Permite escolher cliente existente ou cadastrar novo rapidamente (codigo, nome, CNPJ). Cliente novo e criado dentro da mesma transacao.
  - Garante que todos os IDs selecionados ainda estao em estoque antes de prosseguir.
  - Cria operacao `SAIDA` com responsavel `performed_by`, vincula itens em `equipment_operation_items` e atualiza equipamentos para `alocado`, registrando cliente atual e auditoria (`updated_by`, `updated_at`).
  - Suporta anotacoes gerais por operacao.
- **Retorno (`public/retornos.php`)**:
  - Lista equipamentos `alocado` agrupados por cliente.
  - Usuario seleciona quais itens retornaram e descreve acessorios recebidos (fonte, HDMI, controle), condicao pos-retorno (`ok`, `manutencao`, `descartar`) e observacoes.
  - Valida que todos os itens escolhidos pertencem ao mesmo cliente (requisito de negocio) e continuam alocados.
  - Cria operacao `RETORNO`, grava anexos em `equipment_operation_items` (campos accessories_* e condition_after_return) e atualiza cada equipamento:
    - condicao padrao vira `usado`.
    - status pos-retorno depende da condicao informada (`em_estoque`, `manutencao`, `baixado`).
    - limpa `current_client_id`.
  - Aceita anotacoes gerais da operacao e por equipamento.

### 3.6 Clientes (`public/clientes.php`)

- Qualquer usuario pode pesquisar clientes (codigo, nome, CNPJ). Admin/gestor conseguem criar ou editar.
- Campos mantidos: codigo, nome, CNPJ, contato, telefone, email, endereco, cidade, estado.
- Previne duplicidade pelo `client_code` (UNIQUE no banco) e retorna erros amigaveis quando o MySQL dispara codigo 1062.

### 3.7 Usuarios (`public/usuarios.php`, admin only)

- Listagem com ultimo acesso, status e botoes de acoes.
- Formulario para criar usuario (nome, email, perfil, senha inicial). Usa Bcrypt para hashes e trata e-mails duplicados.
- Reset de senha inline (campo + botao). Regras extras:
  - Validacao CSRF em todas as acoes.
  - Nao permite excluir o proprio usuario logado.

### 3.8 Relatorios (`public/relatorios.php`)

- Filtros por periodo (datas inicio/fim) e tipo de operacao.
- Cards de resumo com total de itens e operacoes por tipo.
- Tabela com ate 200 movimentacoes (data, tipo, quantidade de itens, cliente, responsavel, observacao). Usa `COUNT(eoi.id)` para contabilizar itens.
- Exportacao nao automatizada; README sugere evolucao para CSV.

### 3.9 Perfil pessoal (`public/perfil.php`)

- Qualquer usuario pode atualizar nome e telefone; email e perfil sao fixos.
- Mudanca de senha exige senha atual + confirmacao e rehash Bcrypt.

### 3.10 Configuracoes de modelos (`public/configuracoes.php`)

- Admin/gestor cadastram modelos de equipamentos ativos (`equipment_models`) com categoria (`android_box`, `monitor`, `outro`), marca, modelo e polegadas (quando monitor).
- Lista todos os modelos com status ativo/inativo (flag `is_active`) e respeita UNIQUE `(brand, model_name, monitor_size)`.

### 3.11 Ferramentas auxiliares e UI

- `templates/theme-resources.php` configura tema escuro/claro com Tailwind, Material Icons e Alpine store. Aplica estilos customizados para ambos os modos.
- `templates/header.php`, `sidebar.php`, `topbar.php`, `footer.php`, `mobile-menu.php` padronizam navegacao.
- `assets/`: reservado para arquivos estaticos (imagens, logos); nao contem assets compilados.
- Arquivos de diagnostico:
  - `public/test_db.php` / `scripts/test_db*.php`: verificam conexao PDO e listam tabelas.
  - `test_db_root.php`: apenas inclui `config.php` para checar caminhos.
  - `phpinfo.php`: exposto somente para troubleshooting.
  - `tmp_dashboard.html` + `scripts/render_dashboard.php`: renderizam dashboard para analise offline (script define `$_SESSION['user_id']=1` para simular login).

## 4. Fluxos e regras de negocio

1. **Entrada de equipamento**
   - Requer perfil admin/gestor.
   - Numero de serie obrigatorio; se `asset_tag` vazio, assume serie ou gera `TAG-XXXXXX`.
   - MAC eh normalizado para `XX:XX:XX:XX:XX:XX` e validado por regex.
   - Modelo precisa estar ativo em `equipment_models`.
   - Sempre cria registro em `equipment_operations` (tipo ENTRADA) para rastreabilidade.
   - Campos tecnicos (player_id, versoes, flags) viram anotacoes em `equipment_notes`.
2. **Saida**
   - Somente equipamentos `em_estoque` podem ser selecionados (verificacao com consulta `WHERE status = 'em_estoque'`).
   - Permite criar cliente on-the-fly; exige codigo unico.
   - Atualiza `equipment.status = 'alocado'` e `current_client_id`.
3. **Retorno**
   - Todos os itens de uma operacao precisam pertencer ao mesmo cliente e continuar com status `alocado`.
   - Condicao final direciona proximo status (`ok` -> `em_estoque`, `manutencao` -> `manutencao`, `descartar` -> `baixado`).
   - Atualiza `condition_status` para `usado` e remove vinculo com cliente.
4. **Status manual**
   - Apenas admin/gestor conseguem mudar status diretamente no detalhe do equipamento.
   - Exclusao fisica de equipamento limita-se a admin e ocorre dentro de transacao com `DELETE` dos dependentes.
5. **Clientes**
   - Duplicidade por `client_code` bloqueada no banco; interface traduz erro.
6. **Usuarios**
   - Emails unicos e nao e permitido self-delete.
   - Reset de senha exige nova senha informada manualmente.
7. **Seguranca transversal**
   - Todas as paginas autenticadas chamam `require_login`.
   - CSRF presente em cada formulario POST critico.
   - `sanitize()` (helper) e usado para escapar saidas HTML.
   - Sessao usa cookie proprio (`SESSION_NAME = 'estoque_session'`) com SameSite=Lax.
   - README recomenda remover scripts de diagnostico em producao e mover secrets para variaveis de ambiente.

## 5. Banco de dados (`schema.sql`)

### 5.1 Tabelas e propositos

| Tabela | Objetivo / campos principais |
| --- | --- |
| `users` | Usuarios autenticados; campos: nome, email unico, `password_hash`, `role`, contato, flags de atividade e timestamps. |
| `clients` | Clientes corporativos destino dos equipamentos; inclui codigo unico, dados fiscais e contato. |
| `equipment_models` | Catalogo de modelos (categoria, marca, modelo, polegadas, flag `is_active`). Seeds padrao para boxes Proeletronic/Aquario/Generico e monitores LG. |
| `equipment` | Inventario fisico; referencia modelo, status (`em_estoque`, `alocado`, `manutencao`, `baixado`), condicao (`novo`/`usado`), cliente atual, notas, auditoria (`created_by`/`updated_by`). |
| `equipment_operations` | Cabecalho de movimentacoes; tipo (`ENTRADA`, `SAIDA`, `RETORNO`), data, cliente opcional, observacoes e usuario responsavel. |
| `equipment_operation_items` | Itens vinculados a uma operacao (equipamento, acessorios, condicao pos-retorno, observacoes). Constraint UNIQUE `operation_id + equipment_id`. |
| `equipment_notes` | Anotacoes livres associadas a equipamentos. |

### 5.2 Relacionamentos chaves

- `equipment.model_id` -> `equipment_models.id` (N:1).
- `equipment.current_client_id` -> `clients.id` (N:1, opcional).
- `equipment.created_by` / `updated_by` -> `users.id`.
- `equipment_operations.client_id` -> `clients.id` (opcional).
- `equipment_operations.performed_by` -> `users.id`.
- `equipment_operation_items.operation_id` -> `equipment_operations.id` (cascade delete).
- `equipment_operation_items.equipment_id` -> `equipment.id`.
- `equipment_notes.equipment_id` -> `equipment.id`; `equipment_notes.user_id` -> `users.id`.

### 5.3 Dominios e regras no schema

- Enumeracoes:
  - `users.role`: `admin`, `gestor`, `usuario`.
  - `equipment.status`: `em_estoque`, `alocado`, `manutencao`, `baixado`.
  - `equipment.condition_status`: `novo`, `usado`.
  - `equipment_models.category`: `android_box`, `monitor`, `outro`.
  - `equipment_models.monitor_size`: enum restrito (`42`, `49`, `50`).
  - `equipment_operations.operation_type`: `ENTRADA`, `SAIDA`, `RETORNO`.
  - `equipment_operation_items.condition_after_return`: `ok`, `manutencao`, `descartar`.
- Constraints notaveis:
  - `equipment.asset_tag` unico.
  - `clients.client_code` unico.
  - `equipment_models` possui UNIQUE `(brand, model_name, monitor_size)`.
  - `equipment_operation_items` impede o mesmo equipamento duplicado dentro de uma operacao.

## 6. Scripts e utilitarios

- `scripts/create_admin.php`: cria usuario admin via CLI (uso: `php scripts/create_admin.php "Nome" email senha`).
- `scripts/import_equipment.php`: importa lotes a partir de CSV.
  - Flags `--dry-run` e `--delimiter`.
  - Normaliza cabecalhos, modelos e clientes; cria clientes/modelos inexistentes.
  - Deduplica por numero de serie/asset_tag; atualiza status automaticamente (`alocado` quando ja vem com cliente, `baixado` se marcado como desvinculado).
  - Registra operacoes de entrada e notas tecnicas linha a linha.
- `scripts/apply_schema.py`: aplica `schema.sql` num banco existente usando mysql-connector (ignora `CREATE DATABASE/USE`).
- `scripts/fix_permissions.sh`: ajusta permissoes 755/644 (Unix).
- Diagnosticos:
  - `scripts/test_db.php` e `scripts/test_db_connect.php`.
  - `scripts/check_password.php`, `scripts/reset_password.php`, `scripts/dump_user.php` para testes de credenciais/hashes.
  - `scripts/render_dashboard.php`: render server-side do dashboard para auditar layout (usa `tmp_dashboard.html`).

## 7. Dados de apoio e referencias

- `README.md`: instrucoes gerais de setup, perfis e proximos passos sugeridos (logs, filtros avancados, anexos, automacao de deploy/backups).
- `README_DEPLOY.md`: checklist especifico para HostGator (permissoes, SSL, import, limpeza de arquivos de debug).
- `data/import/equipment_import_sample.csv`: formato esperado para importacao em massa (colunas minimas e opcionais descritas no README).
- `.htaccess` + `.user.ini`: configuram reescrita/seguranca no Apache (HTTPS forcado conforme README).
- Logs `php-server.err.log/php-server.out.log`: auxiliares para suporte em dev.

---

Este documento cobre todas as rotas, fluxos e entidades presentes no repositorio em `c:\Users\hilca\OneDrive\Documentos\Projetos Sites\SIstema Estoque`, servindo como referencia unica para evolucao, auditoria e onboarding de novos colaboradores.
