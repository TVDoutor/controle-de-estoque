# PRD — Sistema de Controle de Estoque

## 1. Visao geral
Aplicacao web em PHP + MySQL para controle de estoque de equipamentos (Android boxes, monitores e outros), com rastreio de movimentacoes (entrada, saida, retorno), clientes, usuarios e dashboards operacionais/administrativos.

## 2. Objetivos de negocio
- Reduzir perdas e inconsistencias de estoque com rastreio completo de movimentacoes.
- Acelerar o ciclo de entrada/saida/retorno com formularios padronizados.
- Melhorar visibilidade operacional por meio de dashboards e relatorios.
- Garantir controle de acesso por perfil (admin/gestor/usuario).

## 3. Personas e necessidades
- **Admin**: administrar usuarios, modelos e permissao total sobre movimentacoes.
- **Gestor**: operar movimentacoes, gerenciar clientes e modelos.
- **Usuario**: consultar estoque, relatorios e atualizar perfil pessoal.

## 4. Escopo
### 4.1 In-scope
- Autenticacao, sessao e controle por perfis.
- Cadastro e manutencao de equipamentos, modelos e clientes.
- Registro de entrada, saida e retorno com rastreio de itens.
- Dashboard operacional e dashboard administrativo.
- Relatorios filtraveis por periodo e tipo de operacao.
- Notas por equipamento e auditoria basica (created_by/updated_by).
- Importacao em massa via CSV.

### 4.2 Out-of-scope (por enquanto)
- Integracao com ERPs externos.
- Exportacao automatica (CSV/BI) em tempo real.
- Workflow de aprovacao ou assinatura digital.
- App mobile offline.

## 5. Requisitos funcionais (RF)
### 5.1 Autenticacao e perfis
- **RF01**: Permitir login com email e senha (hash Bcrypt).
- **RF02**: Bloquear acesso a rotas autenticadas sem sessao valida.
- **RF03**: Perfis devem restringir acesso a modulos sensiveis.
- **RF04**: Implementar protecao CSRF em formularios criticos.

### 5.2 Equipamentos
- **RF05**: Cadastrar equipamento com dados de identificacao (serie, MAC, modelo).
- **RF06**: Listar equipamentos com filtros por status, condicao e modelo.
- **RF07**: Permitir atualizar status manualmente (admin/gestor).
- **RF08**: Exibir historico de movimentacoes e notas por equipamento.
- **RF09**: Permitir exclusao definitiva apenas por admin.

### 5.3 Movimentacoes
- **RF10**: Registrar entrada com operacao vinculada.
- **RF11**: Registrar saida de multiplos equipamentos para um cliente.
- **RF12**: Registrar retorno com acessorios recebidos e condicao pos-retorno.
- **RF13**: Atualizar status automaticamente conforme regras de negocio.

### 5.4 Clientes
- **RF14**: Permitir cadastro e edicao de clientes (admin/gestor).
- **RF15**: Permitir consulta de clientes a todos os perfis.
- **RF16**: Impedir duplicidade por codigo de cliente.

### 5.5 Usuarios
- **RF17**: Criar usuarios com perfis e status.
- **RF18**: Resetar senha de usuario (admin).
- **RF19**: Impedir auto-exclusao do usuario logado.

### 5.6 Dashboards e relatorios
- **RF20**: Exibir KPIs de estoque, operacoes e clientes.
- **RF21**: Exibir graficos de tendencia e distribuicao.
- **RF22**: Listar relatorios com filtros por periodo e tipo.

### 5.7 Importacao em massa
- **RF23**: Importar equipamentos via CSV.
- **RF24**: Permitir modo simulacao (dry-run).
- **RF25**: Criar modelos/clientes inexistentes durante importacao.

## 6. Requisitos nao funcionais (RNF)
- **RNF01**: PHP 8.1+ e MySQL 5.7+/MariaDB 10.4+.
- **RNF02**: Resposta de pagina em ate 2s para consultas padrao.
- **RNF03**: Sessao segura com SameSite=Lax e HTTPS em producao.
- **RNF04**: Auditoria basica (created_by/updated_by, last_login).
- **RNF05**: Logs de erros habilitados e rotacao semanal recomendada.

## 7. Regras de negocio
- Equipamento somente sai se status = `em_estoque`.
- Retorno exige todos os itens do mesmo cliente.
- Condicao pos-retorno define status final:
  - `ok` -> `em_estoque`
  - `manutencao` -> `manutencao`
  - `descartar` -> `baixado`
- Numero de serie obrigatorio na entrada.
- Exclusao fisica somente por admin.

## 8. Dados e entidades
Principais tabelas:
- `users`, `clients`, `equipment_models`, `equipment`,
- `equipment_operations`, `equipment_operation_items`,
- `equipment_notes`.

## 9. Fluxos principais
1. **Login**: usuario autentica e acessa dashboard.
2. **Entrada**: cadastro de equipamento + operacao ENTRADA.
3. **Saida**: selecao de itens em estoque + operacao SAIDA.
4. **Retorno**: selecao por cliente + condicao e acessorios.
5. **Relatorios**: filtros por periodo e tipo.
6. **Importacao**: carga em massa via CSV.

## 10. Metricas de sucesso
- Reducao de divergencias de estoque.
- Tempo medio de registro de saida/retorno menor que 2 minutos.
- 100% das movimentacoes registradas com usuario responsavel.

## 11. Riscos e mitigacoes
- **Risco**: inconsistencias por edicao manual.
  - **Mitigacao**: trilhas de auditoria por operacao e status.
- **Risco**: exposicao de scripts de diagnostico.
  - **Mitigacao**: remover arquivos em producao e restringir acesso.
- **Risco**: credenciais em arquivo.
  - **Mitigacao**: migrar para variaveis de ambiente.

## 12. Dependencias e premissas
- Banco MySQL acessivel ao servidor web.
- Sessao PHP com permissao de escrita.
- Equipe de operacao treinada para regras de negocio.

## 13. Criterios de aceite
- Login e controle por perfis funcionando em todas as rotas.
- Entrada/saida/retorno atualizam estoque corretamente.
- Dashboards e relatorios exibem dados coerentes com o banco.
- Importacao CSV valida dados e registra operacoes.

## 14. Roadmap sugerido (proximos passos)
- Exportacao CSV de relatorios.
- Logs de auditoria detalhados por campo.
- Filtros avancados na listagem de equipamentos.
- Rotina de backup automatizado.
