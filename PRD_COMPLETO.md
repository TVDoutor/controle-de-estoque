# PRD - Sistema de Controle de Estoque
## Product Requirements Document (Documento de Requisitos do Produto)

**Versão:** 2.0  
**Data:** Janeiro 2026  
**Status:** Em Produção

---

## 1. Visão Geral

### 1.1 Descrição do Produto
Sistema web de controle de estoque desenvolvido em PHP 8.1+ e MySQL 5.7+/MariaDB 10.4+ para gerenciamento completo do ciclo de vida de equipamentos eletrônicos (Android boxes, monitores e outros dispositivos). O sistema oferece rastreabilidade completa de movimentações (entrada, saída, retorno), gestão de clientes, controle de acesso por perfis e dashboards analíticos.

### 1.2 Objetivos Estratégicos
- **Rastreabilidade Total**: Garantir rastreamento completo de cada equipamento desde a entrada até a baixa
- **Operacionalidade**: Reduzir tempo de registro de movimentações de 10+ minutos para menos de 2 minutos
- **Visibilidade**: Fornecer dashboards e relatórios em tempo real para tomada de decisão
- **Segurança**: Implementar controle de acesso granular por perfis de usuário
- **Auditoria**: Manter histórico completo de todas as operações com rastreamento de responsáveis

### 1.3 Público-Alvo
- **Administradores**: Gestão completa do sistema, usuários e configurações
- **Gestores**: Operação diária de movimentações e gestão de clientes
- **Usuários**: Consulta de estoque e relatórios

---

## 2. Personas e Necessidades

### 2.1 Administrador
**Perfil:** Responsável pela configuração e manutenção do sistema  
**Necessidades:**
- Gerenciar usuários e permissões
- Configurar modelos de equipamentos
- Acesso total a todas as funcionalidades
- Visualizar métricas administrativas
- Excluir equipamentos quando necessário

### 2.2 Gestor
**Perfil:** Operador responsável pelas movimentações diárias  
**Necessidades:**
- Registrar entradas de equipamentos
- Registrar saídas para clientes
- Processar retornos de equipamentos
- Gerenciar clientes
- Visualizar estoque e relatórios

### 2.3 Usuário
**Perfil:** Consultor com acesso limitado  
**Necessidades:**
- Visualizar equipamentos em estoque
- Consultar relatórios
- Visualizar detalhes de equipamentos
- Adicionar anotações
- Atualizar perfil pessoal

---

## 3. Escopo do Produto

### 3.1 Funcionalidades Incluídas (In-Scope)

#### 3.1.1 Autenticação e Segurança
- ✅ Login com email e senha (hash Bcrypt)
- ✅ Controle de sessão com timeout configurável
- ✅ Proteção CSRF em formulários críticos
- ✅ Controle de acesso por perfis (admin, gestor, usuário)
- ✅ Recuperação de senha (estrutura básica)
- ✅ Logout seguro

#### 3.1.2 Gestão de Equipamentos
- ✅ Cadastro de equipamentos com dados completos
- ✅ Listagem com filtros avançados (status, condição, modelo, busca livre)
- ✅ Visualização detalhada de equipamentos
- ✅ Histórico completo de movimentações
- ✅ Sistema de anotações por equipamento
- ✅ Atualização manual de status (admin/gestor)
- ✅ Exclusão física de equipamentos (admin)
- ✅ Rastreamento de acessórios (fonte, HDMI, controle remoto)

#### 3.1.3 Movimentações
- ✅ **Entrada**: Cadastro de novos equipamentos com registro automático de operação
- ✅ **Saída**: Registro de saída de múltiplos equipamentos para um cliente
- ✅ **Retorno**: Processamento de retorno com controle de acessórios e condição
- ✅ Atualização automática de status conforme regras de negócio
- ✅ Vinculação automática de equipamentos a clientes

#### 3.1.4 Gestão de Clientes
- ✅ Cadastro e edição de clientes
- ✅ Campos: código único, nome, CNPJ, contato, telefone, email, endereço completo
- ✅ Prevenção de duplicidade por código
- ✅ Criação rápida durante registro de saída

#### 3.1.5 Gestão de Usuários
- ✅ Criação de usuários com perfis
- ✅ Ativação/desativação de usuários
- ✅ Reset de senha
- ✅ Visualização de último acesso
- ✅ Prevenção de auto-exclusão

#### 3.1.6 Modelos de Equipamentos
- ✅ Cadastro de modelos (categoria, marca, modelo, tamanho)
- ✅ Categorias: Android Box, Monitor, Outro
- ✅ Tamanhos de monitores: 42", 49", 50"
- ✅ Ativação/desativação de modelos
- ✅ Prevenção de duplicidade

#### 3.1.7 Dashboards
- ✅ **Dashboard Operacional**: KPIs, gráficos de tendência, distribuições, histórico recente
- ✅ **Dashboard Administrativo**: Métricas de usuários, distribuição por perfil, operações recentes
- ✅ Filtros por período (30, 90, 365 dias)
- ✅ Gráficos interativos (Chart.js)

#### 3.1.8 Relatórios
- ✅ Filtros por período (data início/fim)
- ✅ Filtros por tipo de operação (ENTRADA, SAÍDA, RETORNO)
- ✅ Cards de resumo com totais
- ✅ Tabela detalhada de movimentações
- ✅ Limite de 200 registros por consulta

#### 3.1.9 Importação em Massa
- ✅ Importação via CSV
- ✅ Modo simulação (dry-run)
- ✅ Criação automática de clientes e modelos inexistentes
- ✅ Normalização de dados (MAC, séries)
- ✅ Validação de dados antes da importação
- ✅ Suporte a múltiplos delimitadores (vírgula, ponto-e-vírgula, tab)

#### 3.1.10 Interface e UX
- ✅ Design responsivo (mobile-first)
- ✅ Tema claro/escuro (toggle)
- ✅ Navegação por sidebar
- ✅ Feedback visual (mensagens flash)
- ✅ Validação de formulários em tempo real
- ✅ Interface moderna com Tailwind CSS

### 3.2 Funcionalidades Futuras (Out-of-Scope)

#### 3.2.1 Curto Prazo
- ⏳ Exportação CSV de relatórios
- ⏳ Logs de auditoria detalhados por campo
- ⏳ Filtros avançados na listagem (por cliente, intervalo de datas)
- ⏳ Sistema de anexos (comprovantes, fotos)
- ⏳ Backup automatizado do banco de dados

#### 3.2.2 Médio Prazo
- ⏳ Integração com ERPs externos
- ⏳ API REST para integrações
- ⏳ Notificações por email
- ⏳ Workflow de aprovação para operações críticas
- ⏳ Dashboard customizável por usuário

#### 3.2.3 Longo Prazo
- ⏳ Aplicativo mobile (iOS/Android)
- ⏳ Modo offline
- ⏳ Leitura de código de barras via câmera
- ⏳ Integração com sistemas de impressão de etiquetas
- ⏳ Business Intelligence (BI) integrado

---

## 4. Requisitos Funcionais Detalhados

### 4.1 Autenticação e Autorização

#### RF001 - Login de Usuário
**Prioridade:** Crítica  
**Descrição:** Sistema deve permitir login com email e senha  
**Critérios de Aceite:**
- Campo de email aceita formato válido
- Campo de senha é obrigatório
- Validação de credenciais contra banco de dados
- Hash de senha usando Bcrypt
- Atualização de `last_login` após login bem-sucedido
- Regeneração de ID de sessão após login
- Mensagem de erro genérica para credenciais inválidas (sem revelar se email existe)

#### RF002 - Controle de Sessão
**Prioridade:** Crítica  
**Descrição:** Sistema deve gerenciar sessões de usuário de forma segura  
**Critérios de Aceite:**
- Sessão iniciada apenas após login válido
- Cookie de sessão com flags `HttpOnly` e `Secure` (quando HTTPS)
- `SameSite=Lax` para proteção CSRF
- Timeout configurável via `SESSION_IDLE_TIMEOUT`
- Logout automático por inatividade
- Regeneração de ID de sessão após login

#### RF003 - Controle de Acesso por Perfis
**Prioridade:** Crítica  
**Descrição:** Sistema deve restringir acesso a funcionalidades por perfil  
**Critérios de Aceite:**
- Três perfis: `admin`, `gestor`, `usuario`
- Admin: acesso total a todas as funcionalidades
- Gestor: acesso a movimentações, clientes, modelos (sem gestão de usuários)
- Usuário: acesso apenas a visualizações e perfil pessoal
- Redirecionamento para login quando não autenticado
- Erro 403 quando perfil insuficiente
- Função `require_login()` aplicada em todas as páginas protegidas

#### RF004 - Proteção CSRF
**Prioridade:** Alta  
**Descrição:** Sistema deve proteger formulários contra ataques CSRF  
**Critérios de Aceite:**
- Token CSRF gerado por sessão
- Token incluído em todos os formulários POST críticos
- Validação de token antes de processar requisições
- Mensagem de erro quando token inválido ou expirado
- Regeneração de token após uso (opcional, implementação atual mantém token)

### 4.2 Gestão de Equipamentos

#### RF005 - Cadastro de Equipamento
**Prioridade:** Crítica  
**Descrição:** Sistema deve permitir cadastro de novos equipamentos  
**Critérios de Aceite:**
- Número de série obrigatório
- Modelo obrigatório (deve estar ativo)
- Etiqueta interna (se vazio, usa número de série ou gera TAG-XXXXXX)
- Endereço MAC opcional (formato alfanumérico XX:XX:XX:XX:XX:XX)
- Condição: novo ou usado
- Data de entrada obrigatória
- Lote opcional
- Observações opcionais
- Campos técnicos opcionais: ID do Player, ID legado, versão OS, versão App
- Flags: equipamento descartado, equipamento desvinculado
- Criação automática de operação ENTRADA
- Se `is_discarded` marcado, status inicial = `baixado`
- Validação de formato MAC (alfanumérico, 12 caracteres)
- Validação de modelo ativo

#### RF006 - Listagem de Equipamentos
**Prioridade:** Alta  
**Descrição:** Sistema deve listar equipamentos com filtros  
**Critérios de Aceite:**
- Exibição de: etiqueta, modelo, série, MAC, status, condição, cliente atual
- Filtro por status (em_estoque, alocado, manutenção, baixado)
- Filtro por condição (novo, usado)
- Busca livre (etiqueta, série, MAC)
- Filtro por modelo/marca
- Ordenação por colunas clicáveis
- Paginação ou limite de resultados
- Badges coloridos para status
- Link para detalhes de cada equipamento

#### RF007 - Detalhes de Equipamento
**Prioridade:** Alta  
**Descrição:** Sistema deve exibir informações detalhadas de um equipamento  
**Critérios de Aceite:**
- Dados do modelo (categoria, marca, modelo, tamanho)
- Dados do equipamento (etiqueta, série, MAC, status, condição, data entrada)
- Cliente atual (quando alocado)
- Histórico completo de movimentações
- Acessórios recebidos em cada retorno
- Condição após retorno
- Bloco de anotações (todas as notas do equipamento)
- Ações disponíveis conforme perfil:
  - Todos: adicionar anotação
  - Admin/Gestor: atualizar status manualmente
  - Admin: excluir equipamento

#### RF008 - Atualização de Status Manual
**Prioridade:** Média  
**Descrição:** Admin/Gestor deve poder atualizar status manualmente  
**Critérios de Aceite:**
- Disponível apenas para admin e gestor
- Opções: em_estoque, alocado, manutenção, baixado
- Atualização de `updated_by` e `updated_at`
- Validação de status válido
- Feedback de sucesso/erro

#### RF009 - Exclusão de Equipamento
**Prioridade:** Média  
**Descrição:** Admin deve poder excluir equipamentos permanentemente  
**Critérios de Aceite:**
- Disponível apenas para admin
- Exclusão em cascata de dependentes:
  - `equipment_operation_items`
  - `equipment_notes`
  - `equipment`
- Execução dentro de transação
- Confirmação antes de excluir
- Feedback de sucesso/erro

#### RF010 - Sistema de Anotações
**Prioridade:** Média  
**Descrição:** Usuários devem poder adicionar anotações a equipamentos  
**Critérios de Aceite:**
- Qualquer usuário autenticado pode adicionar anotação
- Anotação vinculada a equipamento e usuário
- Timestamp automático
- Exibição em ordem cronológica
- Texto livre (sem limite de caracteres definido)

### 4.3 Movimentações

#### RF011 - Registro de Entrada
**Prioridade:** Crítica  
**Descrição:** Sistema deve registrar entrada de equipamentos  
**Critérios de Aceite:**
- Formulário dividido em seções lógicas
- Validação de campos obrigatórios
- Criação de registro em `equipment`
- Criação automática de operação `ENTRADA`
- Vinculação de item à operação
- Registro de detalhes técnicos como anotação
- Status inicial conforme flags (descartado = baixado)
- Transação atômica (rollback em caso de erro)

#### RF012 - Registro de Saída
**Prioridade:** Crítica  
**Descrição:** Sistema deve registrar saída de equipamentos para clientes  
**Critérios de Aceite:**
- Listagem apenas de equipamentos com status `em_estoque`
- Seleção múltipla de equipamentos
- Escolha de cliente existente ou criação rápida
- Validação de que todos os equipamentos ainda estão em estoque
- Criação de operação `SAIDA`
- Vinculação de itens à operação
- Atualização de status para `alocado`
- Atualização de `current_client_id`
- Atualização de `updated_by` e `updated_at`
- Observações opcionais por operação
- Transação atômica

#### RF013 - Registro de Retorno
**Prioridade:** Crítica  
**Descrição:** Sistema deve processar retorno de equipamentos  
**Critérios de Aceite:**
- Listagem de equipamentos `alocado` agrupados por cliente
- Seleção de equipamentos do mesmo cliente
- Campos por equipamento:
  - Acessórios recebidos (fonte, HDMI, controle remoto)
  - Condição após retorno (ok, manutenção, descartar)
  - Observações específicas
- Validação de que todos os itens pertencem ao mesmo cliente
- Validação de que itens ainda estão alocados
- Criação de operação `RETORNO`
- Atualização de equipamentos:
  - `condition_status` = `usado`
  - Status conforme condição após retorno:
    - `ok` → `em_estoque`
    - `manutenção` → `manutenção`
    - `descartar` → `baixado`
  - `current_client_id` = NULL
- Observações gerais opcionais
- Transação atômica

### 4.4 Gestão de Clientes

#### RF014 - Cadastro de Cliente
**Prioridade:** Alta  
**Descrição:** Sistema deve permitir cadastro de clientes  
**Critérios de Aceite:**
- Campos: código único, nome, CNPJ, contato, telefone, email, endereço, cidade, estado
- Código obrigatório e único
- Nome obrigatório
- Demais campos opcionais
- Prevenção de duplicidade por código
- Mensagem de erro amigável em caso de duplicidade
- Criação rápida durante registro de saída

#### RF015 - Edição de Cliente
**Prioridade:** Alta  
**Descrição:** Sistema deve permitir edição de clientes existentes  
**Critérios de Aceite:**
- Apenas admin e gestor podem editar
- Todos os campos editáveis exceto código (único)
- Validação de dados
- Feedback de sucesso/erro

#### RF016 - Listagem de Clientes
**Prioridade:** Média  
**Descrição:** Sistema deve listar clientes com busca  
**Critérios de Aceite:**
- Busca por código, nome ou CNPJ
- Exibição de dados principais
- Link para edição (admin/gestor)
- Acesso de leitura para todos os perfis

### 4.5 Gestão de Usuários

#### RF017 - Criação de Usuário
**Prioridade:** Alta  
**Descrição:** Admin deve poder criar novos usuários  
**Critérios de Aceite:**
- Campos: nome, email, perfil, senha inicial, telefone (opcional)
- Email único e válido
- Perfil: admin, gestor ou usuario
- Hash de senha com Bcrypt
- Status ativo por padrão
- Validação de email duplicado
- Feedback de sucesso/erro

#### RF018 - Reset de Senha
**Prioridade:** Média  
**Descrição:** Admin deve poder resetar senha de usuários  
**Critérios de Aceite:**
- Disponível apenas para admin
- Campo para nova senha
- Hash com Bcrypt
- Atualização de `updated_at`
- Feedback de sucesso

#### RF019 - Ativação/Desativação de Usuário
**Prioridade:** Média  
**Descrição:** Admin deve poder ativar/desativar usuários  
**Critérios de Aceite:**
- Toggle de status ativo/inativo
- Usuário desativado não pode fazer login
- Visualização de status na listagem
- Prevenção de auto-desativação do próprio usuário logado

#### RF020 - Prevenção de Auto-Exclusão
**Prioridade:** Alta  
**Descrição:** Sistema deve impedir que usuário exclua a si mesmo  
**Critérios de Aceite:**
- Validação antes de exclusão
- Mensagem de erro se tentar excluir próprio usuário
- Botão de exclusão desabilitado para próprio usuário

### 4.6 Modelos de Equipamentos

#### RF021 - Cadastro de Modelo
**Prioridade:** Alta  
**Descrição:** Admin/Gestor deve poder cadastrar modelos  
**Critérios de Aceite:**
- Categoria: Android Box, Monitor, Outro
- Marca obrigatória
- Nome do modelo obrigatório
- Tamanho (apenas para monitores): 42", 49", 50"
- Status ativo/inativo
- Prevenção de duplicidade (marca + modelo + tamanho)
- Validação de campos

#### RF022 - Listagem de Modelos
**Prioridade:** Média  
**Descrição:** Sistema deve listar modelos com status  
**Critérios de Aceite:**
- Exibição de categoria, marca, modelo, tamanho, status
- Filtro por categoria
- Filtro por status (ativo/inativo)
- Edição disponível (admin/gestor)
- Apenas modelos ativos aparecem em cadastros

### 4.7 Dashboards

#### RF023 - Dashboard Operacional
**Prioridade:** Alta  
**Descrição:** Sistema deve exibir dashboard com KPIs e gráficos  
**Critérios de Aceite:**
- KPIs:
  - Total de equipamentos
  - Por status (em_estoque, alocado, manutenção, baixado)
  - Total de clientes
- Janela de tempo configurável (30, 90, 365 dias)
- Gráficos:
  - Operações por tipo (entrada, saída, retorno)
  - Tendência de saídas (últimos 12 meses)
  - Distribuição geográfica (por estado)
  - Condição dos equipamentos
  - Top modelos por movimentação
- Lista de movimentações recentes
- Atalhos para funcionalidades principais
- Gráficos interativos (Chart.js)

#### RF024 - Dashboard Administrativo
**Prioridade:** Média  
**Descrição:** Admin deve ter dashboard específico com métricas administrativas  
**Critérios de Aceite:**
- Total de usuários
- Distribuição por perfil
- Status de equipamentos (resumo)
- Gráfico de operações (últimos 14 dias)
- Últimas movimentações
- Atalhos para gestão de usuários, equipamentos e relatórios

### 4.8 Relatórios

#### RF025 - Relatórios Filtrados
**Prioridade:** Alta  
**Descrição:** Sistema deve gerar relatórios com filtros  
**Critérios de Aceite:**
- Filtro por período (data início e fim)
- Filtro por tipo de operação (ENTRADA, SAÍDA, RETORNO)
- Cards de resumo:
  - Total de operações por tipo
  - Total de itens por tipo
- Tabela detalhada:
  - Data da operação
  - Tipo
  - Quantidade de itens
  - Cliente (quando aplicável)
  - Responsável
  - Observações
- Limite de 200 registros por consulta
- Ordenação por data (mais recente primeiro)

### 4.9 Importação em Massa

#### RF026 - Importação CSV
**Prioridade:** Média  
**Descrição:** Sistema deve importar equipamentos via CSV  
**Critérios de Aceite:**
- Suporte a múltiplos delimitadores (vírgula, ponto-e-vírgula, tab)
- Detecção automática de delimitador
- Normalização de cabeçalhos
- Mapeamento de colunas esperadas:
  - Dados do Cliente Alocado
  - ID do Player
  - ID Legado do Player
  - Modelo do Aparelho
  - Número de Série (obrigatório)
  - Endereço MAC
  - Versão do OS
  - Versão do App
  - Localização Lat Long
  - Equipamento desvinculado
- Validação de dados:
  - Número de série obrigatório
  - MAC válido (alfanumérico, 12 caracteres)
  - Modelo existente ou criação automática
- Criação automática de:
  - Clientes inexistentes
  - Modelos inexistentes
- Deduplicação por série/etiqueta
- Atualização de equipamentos existentes
- Criação de operações ENTRADA
- Registro de detalhes técnicos como anotações
- Modo simulação (dry-run) sem gravação
- Relatório de importação:
  - Total processado
  - Inseridos
  - Atualizados
  - Ignorados
  - Erros

---

## 5. Requisitos Não Funcionais

### 5.1 Performance

#### RNF001 - Tempo de Resposta
**Prioridade:** Alta  
**Descrição:** Páginas devem carregar em tempo razoável  
**Critérios:**
- Tempo de resposta de páginas simples: < 1 segundo
- Tempo de resposta de páginas com consultas complexas: < 2 segundos
- Tempo de resposta de dashboards: < 3 segundos

#### RNF002 - Escalabilidade
**Prioridade:** Média  
**Descrição:** Sistema deve suportar crescimento de dados  
**Critérios:**
- Suporte a pelo menos 10.000 equipamentos
- Suporte a pelo menos 1.000 clientes
- Suporte a pelo menos 100 usuários simultâneos

### 5.2 Segurança

#### RNF003 - Proteção de Dados
**Prioridade:** Crítica  
**Descrição:** Sistema deve proteger dados sensíveis  
**Critérios:**
- Senhas armazenadas com hash Bcrypt (cost mínimo 10)
- Proteção CSRF em formulários críticos
- Sanitização de saídas HTML (função `sanitize()`)
- Validação de entrada em todos os formulários
- Prepared statements para todas as consultas SQL
- HTTPS obrigatório em produção

#### RNF004 - Controle de Acesso
**Prioridade:** Crítica  
**Descrição:** Sistema deve controlar acesso por perfis  
**Critérios:**
- Verificação de autenticação em todas as páginas protegidas
- Verificação de perfil antes de ações sensíveis
- Sessão segura com regeneração de ID
- Timeout de sessão configurável
- Logout automático por inatividade

#### RNF005 - Auditoria
**Prioridade:** Alta  
**Descrição:** Sistema deve manter rastreabilidade de ações  
**Critérios:**
- Registro de `created_by` e `updated_by` em entidades principais
- Registro de `last_login` em usuários
- Histórico completo de movimentações
- Timestamps em todas as tabelas principais

### 5.3 Usabilidade

#### RNF006 - Interface Responsiva
**Prioridade:** Alta  
**Descrição:** Interface deve funcionar em diferentes dispositivos  
**Critérios:**
- Layout responsivo (mobile-first)
- Funcionalidade completa em desktop, tablet e mobile
- Navegação adaptável (sidebar colapsável em mobile)

#### RNF007 - Acessibilidade
**Prioridade:** Média  
**Descrição:** Interface deve ser acessível  
**Critérios:**
- Contraste adequado de cores
- Navegação por teclado
- Labels descritivos em formulários
- Mensagens de erro claras

#### RNF008 - Feedback Visual
**Prioridade:** Alta  
**Descrição:** Sistema deve fornecer feedback claro ao usuário  
**Critérios:**
- Mensagens de sucesso/erro visíveis
- Indicadores de carregamento
- Validação em tempo real de formulários
- Confirmações para ações destrutivas

### 5.4 Confiabilidade

#### RNF009 - Disponibilidade
**Prioridade:** Alta  
**Descrição:** Sistema deve estar disponível quando necessário  
**Critérios:**
- Uptime mínimo de 99% (excluindo manutenções programadas)
- Tratamento de erros sem expor informações sensíveis
- Logs de erro para diagnóstico

#### RNF010 - Integridade de Dados
**Prioridade:** Crítica  
**Descrição:** Sistema deve manter integridade dos dados  
**Critérios:**
- Transações atômicas para operações críticas
- Constraints de banco de dados (UNIQUE, FOREIGN KEY)
- Validação de dados antes de gravação
- Rollback automático em caso de erro

### 5.5 Manutenibilidade

#### RNF011 - Código Limpo
**Prioridade:** Média  
**Descrição:** Código deve ser legível e manutenível  
**Critérios:**
- Estrutura organizada em diretórios lógicos
- Funções reutilizáveis
- Comentários quando necessário
- Nomenclatura consistente

#### RNF012 - Documentação
**Prioridade:** Média  
**Descrição:** Sistema deve ter documentação adequada  
**Critérios:**
- README com instruções de instalação
- Documentação de schema do banco
- Comentários em código complexo
- Guia de deploy

---

## 6. Regras de Negócio Detalhadas

### 6.1 Equipamentos

#### RN001 - Status de Equipamento
**Regra:** Equipamento pode estar em um dos seguintes status:
- `em_estoque`: Disponível para saída
- `alocado`: Vinculado a um cliente (saído)
- `manutenção`: Em reparo ou manutenção
- `baixado`: Descartado ou dado como perdido

**Transições:**
- `em_estoque` → `alocado` (via saída)
- `alocado` → `em_estoque` (via retorno com condição "ok")
- `alocado` → `manutenção` (via retorno com condição "manutenção")
- `alocado` → `baixado` (via retorno com condição "descartar")
- Qualquer status → `manutenção` ou `baixado` (via atualização manual - admin/gestor)

#### RN002 - Condição de Equipamento
**Regra:** Equipamento pode estar em uma das seguintes condições:
- `novo`: Equipamento novo, nunca usado
- `usado`: Equipamento já utilizado

**Transições:**
- `novo` → `usado` (automaticamente após retorno)
- Condição não pode voltar de `usado` para `novo`

#### RN003 - Número de Série
**Regra:** Número de série é obrigatório no cadastro  
**Exceção:** Se não informado, sistema gera `asset_tag` aleatório

#### RN004 - Endereço MAC
**Regra:** Endereço MAC é opcional e deve ser alfanumérico  
**Formato:** XX:XX:XX:XX:XX:XX (12 caracteres alfanuméricos, separados por dois pontos)  
**Normalização:** Letras maiúsculas, remoção de caracteres especiais

#### RN005 - Etiqueta Interna
**Regra:** Etiqueta interna deve ser única  
**Geração automática:**
- Se vazio e série informado: usa número de série
- Se vazio e série não informado: gera TAG-XXXXXX (hexadecimal)

### 6.2 Movimentações

#### RN006 - Saída de Equipamento
**Regra:** Apenas equipamentos com status `em_estoque` podem ser selecionados para saída  
**Validação:** Sistema verifica status antes de processar saída  
**Atualização automática:**
- Status → `alocado`
- `current_client_id` → ID do cliente
- `updated_by` → ID do usuário responsável
- `updated_at` → timestamp atual

#### RN007 - Retorno de Equipamento
**Regra:** Todos os equipamentos de uma operação de retorno devem pertencer ao mesmo cliente  
**Validação:** Sistema verifica `current_client_id` antes de processar  
**Atualização automática:**
- `condition_status` → `usado`
- Status conforme `condition_after_return`:
  - `ok` → `em_estoque`
  - `manutenção` → `manutenção`
  - `descartar` → `baixado`
- `current_client_id` → NULL

#### RN008 - Entrada de Equipamento Descartado
**Regra:** Se flag `is_discarded` marcado no cadastro, equipamento entra com status `baixado`  
**Aplicação:** Imediata no momento do cadastro

### 6.3 Clientes

#### RN009 - Código de Cliente
**Regra:** Código de cliente deve ser único  
**Validação:** Constraint UNIQUE no banco de dados  
**Mensagem:** Erro amigável quando duplicidade detectada

#### RN010 - Criação Rápida de Cliente
**Regra:** Cliente pode ser criado durante registro de saída  
**Campos mínimos:** Código e nome  
**Campos opcionais:** CNPJ, contato, telefone, email, endereço completo

### 6.4 Usuários

#### RN011 - Auto-Exclusão
**Regra:** Usuário não pode excluir a si mesmo  
**Validação:** Comparação de `user_id` da sessão com ID do usuário a excluir  
**Mensagem:** Erro claro quando tentativa detectada

#### RN012 - Reset de Senha
**Regra:** Apenas admin pode resetar senha de outros usuários  
**Processo:** Nova senha informada manualmente, hash com Bcrypt, atualização de `updated_at`

### 6.5 Modelos

#### RN013 - Modelo Ativo
**Regra:** Apenas modelos com `is_active = 1` aparecem em listagens de seleção  
**Aplicação:** Filtro em todos os selects de modelo

#### RN014 - Duplicidade de Modelo
**Regra:** Não pode existir modelo duplicado com mesma marca, nome e tamanho  
**Validação:** Constraint UNIQUE `(brand, model_name, monitor_size)`  
**Exceção:** Tamanho NULL não conflita com tamanho definido

---

## 7. Arquitetura e Estrutura Técnica

### 7.1 Stack Tecnológico

#### Backend
- **Linguagem:** PHP 8.1 ou superior
- **Extensões:** PDO MySQL, OpenSSL, mbstring
- **Padrão:** MVC simplificado (sem framework)
- **Banco de Dados:** MySQL 5.7+ / MariaDB 10.4+

#### Frontend
- **CSS Framework:** Tailwind CSS (via CDN)
- **JavaScript:** Alpine.js (interatividade), Chart.js (gráficos)
- **Ícones:** Material Icons (via CDN)
- **Build:** Sem build step (CDN direto)

#### Segurança
- **Hash de Senha:** Bcrypt (cost 10+)
- **Sessão:** PHP Sessions com cookies seguros
- **CSRF:** Tokens por sessão
- **Sanitização:** Função `sanitize()` para saídas HTML

### 7.2 Estrutura de Diretórios

```
/
├── public/                 # Arquivos acessíveis via web
│   ├── index.php          # Redirecionamento
│   ├── login.php          # Autenticação
│   ├── dashboard.php      # Dashboard operacional
│   ├── admin_dashboard.php # Dashboard administrativo
│   ├── equipamentos.php   # Listagem de equipamentos
│   ├── equipamento_detalhe.php # Detalhes
│   ├── entrada_cadastrar.php # Cadastro de entrada
│   ├── saida_registrar.php # Registro de saída
│   ├── retornos.php       # Processamento de retorno
│   ├── clientes.php       # Gestão de clientes
│   ├── usuarios.php       # Gestão de usuários (admin)
│   ├── relatorios.php     # Relatórios
│   ├── configuracoes.php   # Modelos de equipamentos
│   ├── perfil.php         # Perfil pessoal
│   ├── logout.php         # Logout
│   └── recuperar_senha.php # Recuperação de senha
│
├── includes/              # Código compartilhado
│   ├── config.php        # Configuração do banco
│   ├── database.php      # Conexão PDO
│   ├── session.php       # Configuração de sessão
│   ├── auth.php          # Autenticação e autorização
│   └── helpers.php       # Funções auxiliares
│
├── templates/            # Templates compartilhados
│   ├── header.php        # Cabeçalho
│   ├── footer.php        # Rodapé
│   ├── sidebar.php       # Menu lateral
│   ├── topbar.php        # Barra superior
│   ├── mobile-menu.php   # Menu mobile
│   └── theme-resources.php # Recursos de tema
│
├── scripts/              # Scripts CLI
│   ├── create_admin.php  # Criar usuário admin
│   ├── import_equipment.php # Importação CSV
│   ├── apply_schema.py   # Aplicar schema
│   └── [outros utilitários]
│
├── data/                 # Dados de apoio
│   └── import/           # CSVs de exemplo
│
├── assets/               # Arquivos estáticos (reservado)
├── schema.sql            # Schema do banco de dados
├── README.md             # Documentação principal
├── MAPEAMENTO_SISTEMA.md # Mapeamento técnico
└── PRD.md                # PRD anterior
```

### 7.3 Banco de Dados

#### Tabelas Principais

**users**
- Armazena usuários do sistema
- Campos: id, name, email (UNIQUE), password_hash, role, phone, is_active, last_login, timestamps

**clients**
- Armazena clientes
- Campos: id, client_code (UNIQUE), name, cnpj, contact_name, phone, email, address, city, state, timestamps

**equipment_models**
- Catálogo de modelos de equipamentos
- Campos: id, category, brand, model_name, monitor_size, is_active, timestamps
- UNIQUE (brand, model_name, monitor_size)

**equipment**
- Inventário de equipamentos
- Campos: id, asset_tag (UNIQUE), model_id (FK), serial_number, mac_address, condition_status, status, entry_date, batch, notes, current_client_id (FK), created_by (FK), updated_by (FK), timestamps

**equipment_operations**
- Cabeçalho de movimentações
- Campos: id, operation_type, operation_date, client_id (FK), notes, performed_by (FK), created_at

**equipment_operation_items**
- Itens de uma movimentação
- Campos: id, operation_id (FK), equipment_id (FK), accessories_power, accessories_hdmi, accessories_remote, condition_after_return, remarks
- UNIQUE (operation_id, equipment_id)

**equipment_notes**
- Anotações por equipamento
- Campos: id, equipment_id (FK), user_id (FK), note, created_at

#### Relacionamentos

```
users (1) ──< equipment (created_by, updated_by)
users (1) ──< equipment_operations (performed_by)
users (1) ──< equipment_notes (user_id)

clients (1) ──< equipment (current_client_id)
clients (1) ──< equipment_operations (client_id)

equipment_models (1) ──< equipment (model_id)

equipment_operations (1) ──< equipment_operation_items (operation_id)
equipment (1) ──< equipment_operation_items (equipment_id)
equipment (1) ──< equipment_notes (equipment_id)
```

### 7.4 Fluxo de Autenticação

1. Usuário acessa página protegida
2. `require_login()` verifica sessão
3. Se não autenticado → redireciona para `login.php`
4. Usuário informa email e senha
5. `login()` valida credenciais
6. Se válido:
   - Atualiza `last_login`
   - Regenera ID de sessão
   - Salva `user_id` e `user_role` na sessão
   - Redireciona para dashboard
7. Se inválido: exibe mensagem genérica

### 7.5 Fluxo de Autorização

1. Página protegida chama `require_login(['admin', 'gestor'])`
2. `enforce_session_timeout()` verifica timeout
3. `current_user()` busca usuário do banco
4. Verifica se perfil está na lista permitida
5. Se não autorizado: retorna 403
6. Se autorizado: renderiza página

---

## 8. Interface e Experiência do Usuário

### 8.1 Design System

#### Cores
- **Tema Escuro (padrão):**
  - Fundo principal: `slate-950`
  - Cards: `slate-900/80`
  - Texto: `slate-100`
  - Destaque: `blue-600`
  - Sucesso: `emerald-500`
  - Erro: `red-500`

- **Tema Claro:**
  - Fundo principal: branco
  - Cards: `slate-50`
  - Texto: `slate-900`
  - Destaque: `blue-600`

#### Tipografia
- **Fonte:** Inter (via Google Fonts)
- **Tamanhos:** xs, sm, base, lg, xl
- **Pesos:** normal (400), medium (500), semibold (600)

#### Componentes
- **Botões:** Rounded-xl, padding adequado, estados hover/disabled
- **Inputs:** Rounded-xl, borda slate-700, foco blue-400
- **Cards:** Rounded-3xl, sombra, borda
- **Badges:** Rounded-full, cores por status

### 8.2 Navegação

#### Sidebar
- Menu lateral fixo (desktop)
- Colapsável
- Ícones Material Icons
- Destaque para item ativo
- Perfil do usuário no rodapé

#### Topbar
- Barra superior com informações do usuário
- Toggle de tema (claro/escuro)
- Botão de logout
- Responsiva (oculta sidebar em mobile)

### 8.3 Feedback Visual

#### Mensagens Flash
- Sucesso: verde com ícone
- Erro: vermelho com ícone
- Aviso: amarelo com ícone
- Exibição temporária (fade out)

#### Validação de Formulários
- Mensagens de erro abaixo dos campos
- Campos inválidos com borda vermelha
- Validação em tempo real (JavaScript)

#### Estados de Carregamento
- Spinner em botões durante submissão
- Desabilitação de formulário durante processamento

---

## 9. Segurança

### 9.1 Autenticação
- Hash de senha: Bcrypt (cost 10+)
- Regeneração de ID de sessão após login
- Timeout de sessão configurável
- Logout automático por inatividade

### 9.2 Autorização
- Verificação de perfil em todas as ações sensíveis
- Função `require_login()` em todas as páginas protegidas
- Verificação de propriedade antes de edições

### 9.3 Proteção CSRF
- Token gerado por sessão
- Token incluído em todos os formulários POST
- Validação antes de processar requisições

### 9.4 Sanitização
- Função `sanitize()` para todas as saídas HTML
- Prepared statements para todas as consultas SQL
- Validação de tipos e formatos

### 9.5 Sessão
- Cookie `HttpOnly` e `Secure` (quando HTTPS)
- `SameSite=Lax` para proteção CSRF
- Nome de sessão customizado
- Regeneração de ID após login

---

## 10. Métricas de Sucesso

### 10.1 Métricas Operacionais
- **Tempo de registro de saída:** < 2 minutos (meta: 1 minuto)
- **Tempo de registro de retorno:** < 3 minutos (meta: 2 minutos)
- **Taxa de movimentações rastreadas:** 100%
- **Precisão de estoque:** > 99%

### 10.2 Métricas de Uso
- **Usuários ativos mensais:** Acompanhar crescimento
- **Operações por dia:** Baseline e tendência
- **Equipamentos cadastrados:** Crescimento do inventário

### 10.3 Métricas Técnicas
- **Tempo de resposta:** < 2s para 95% das requisições
- **Uptime:** > 99%
- **Taxa de erro:** < 0.1%

---

## 11. Riscos e Mitigações

### 11.1 Riscos Técnicos

#### Risco: Inconsistências por Edição Manual
**Probabilidade:** Média  
**Impacto:** Alto  
**Mitigação:**
- Transações atômicas para operações críticas
- Validações antes de atualizações
- Histórico completo de movimentações
- Auditoria (created_by/updated_by)

#### Risco: Exposição de Scripts de Diagnóstico
**Probabilidade:** Baixa  
**Impacto:** Médio  
**Mitigação:**
- Remover arquivos de debug em produção
- Restringir acesso via .htaccess
- Documentar checklist de deploy

#### Risco: Credenciais em Arquivo
**Probabilidade:** Média  
**Impacto:** Alto  
**Mitigação:**
- Migrar para variáveis de ambiente
- Arquivo `config.example.php` sem credenciais
- Documentar boas práticas

### 11.2 Riscos de Negócio

#### Risco: Perda de Dados
**Probabilidade:** Baixa  
**Impacto:** Crítico  
**Mitigação:**
- Backups regulares do banco de dados
- Transações atômicas
- Logs de auditoria

#### Risco: Uso Indevido de Permissões
**Probabilidade:** Média  
**Impacto:** Médio  
**Mitigação:**
- Controle rigoroso de perfis
- Logs de ações administrativas
- Revisão periódica de usuários

---

## 12. Roadmap e Próximos Passos

### 12.1 Fase 1 - Melhorias Imediatas (1-2 meses)
1. ✅ Exportação CSV de relatórios
2. ✅ Logs de auditoria detalhados
3. ✅ Filtros avançados na listagem de equipamentos
4. ✅ Sistema de anexos (comprovantes, fotos)
5. ✅ Backup automatizado do banco

### 12.2 Fase 2 - Funcionalidades Avançadas (3-6 meses)
1. ⏳ API REST para integrações
2. ⏳ Notificações por email
3. ⏳ Workflow de aprovação
4. ⏳ Dashboard customizável
5. ⏳ Integração com ERPs

### 12.3 Fase 3 - Expansão (6-12 meses)
1. ⏳ Aplicativo mobile (iOS/Android)
2. ⏳ Modo offline
3. ⏳ Leitura de código de barras
4. ⏳ Integração com impressoras de etiquetas
5. ⏳ Business Intelligence (BI)

---

## 13. Critérios de Aceite Gerais

### 13.1 Funcionalidade
- ✅ Todas as funcionalidades descritas no PRD estão implementadas
- ✅ Regras de negócio estão corretamente aplicadas
- ✅ Validações funcionam conforme especificado

### 13.2 Segurança
- ✅ Autenticação e autorização funcionando
- ✅ Proteção CSRF implementada
- ✅ Dados sanitizados adequadamente

### 13.3 Performance
- ✅ Páginas carregam em tempo aceitável
- ✅ Consultas otimizadas
- ✅ Sem queries N+1

### 13.4 Usabilidade
- ✅ Interface responsiva
- ✅ Feedback visual adequado
- ✅ Mensagens de erro claras

### 13.5 Documentação
- ✅ README completo
- ✅ Schema documentado
- ✅ Instruções de deploy disponíveis

---

## 14. Glossário

- **Asset Tag:** Etiqueta interna única do equipamento
- **Alocado:** Status de equipamento vinculado a um cliente (saído)
- **Baixado:** Status de equipamento descartado ou dado como perdido
- **CSRF:** Cross-Site Request Forgery (ataque de falsificação de requisição)
- **Dry-run:** Modo de simulação sem gravação de dados
- **MAC Address:** Endereço físico da interface de rede (alfanumérico)
- **PDO:** PHP Data Objects (abstração de banco de dados)
- **Status:** Estado atual do equipamento no sistema
- **Condição:** Estado físico do equipamento (novo/usado)

---

## 15. Referências

- **Documentação PHP:** https://www.php.net/docs.php
- **MySQL Documentation:** https://dev.mysql.com/doc/
- **Tailwind CSS:** https://tailwindcss.com/docs
- **Alpine.js:** https://alpinejs.dev/
- **Chart.js:** https://www.chartjs.org/docs/

---

**Documento mantido por:** Equipe de Desenvolvimento  
**Última atualização:** Janeiro 2026  
**Versão:** 2.0
