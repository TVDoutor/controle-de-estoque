# Arquitetura do Sistema de Controle de Estoque

## 1. Visão Geral

O Sistema de Controle de Estoque é uma aplicação web monolítica desenvolvida em PHP que gerencia o inventário de equipamentos (boxes Android e monitores), suas movimentações (entrada, saída e retorno) e relacionamento com clientes.

### 1.1 Características Principais

- **Arquitetura**: Monolítica tradicional (MVC-like)
- **Padrão de Comunicação**: Request/Response síncrono
- **Renderização**: Server-side rendering (SSR)
- **Estado**: Gerenciado via sessões PHP
- **Interface**: Single Page Application (SPA) com navegação tradicional

---

## 2. Stack Tecnológica

### 2.1 Backend

| Tecnologia | Versão | Uso |
|------------|--------|-----|
| **PHP** | 8.1+ | Linguagem principal do servidor |
| **MySQL/MariaDB** | 5.7+/10.4+ | Banco de dados relacional |
| **PDO** | Nativo | Camada de abstração para acesso ao banco |
| **Bcrypt** | Nativo | Hash de senhas |

### 2.2 Frontend

| Tecnologia | Versão | Uso |
|------------|--------|-----|
| **HTML5** | - | Estrutura das páginas |
| **Tailwind CSS** | CDN | Framework CSS utilitário |
| **Alpine.js** | 3.13.5 | Framework JavaScript reativo (interatividade) |
| **Chart.js** | CDN | Gráficos e visualizações (dashboard) |
| **Material Icons** | CDN | Ícones da interface |

### 2.3 Infraestrutura

| Componente | Descrição |
|------------|-----------|
| **Servidor Web** | Apache/Nginx (DocumentRoot apontando para `public/`) |
| **Sessões PHP** | Gerenciamento de estado do usuário |
| **Cookies** | Armazenamento de preferências (tema, densidade) |

---

## 3. Estrutura de Diretórios

```
/
├── public/                    # Ponto de entrada público (DocumentRoot)
│   ├── index.php            # Redirecionamento inicial
│   ├── login.php            # Autenticação
│   ├── dashboard.php        # Dashboard operacional
│   ├── admin_dashboard.php  # Dashboard administrativo
│   ├── equipamentos.php     # Listagem de equipamentos
│   ├── entrada_cadastrar.php # Cadastro de entrada
│   ├── saida_registrar.php  # Registro de saída
│   ├── retornos.php         # Registro de retorno
│   ├── clientes.php         # Gestão de clientes
│   ├── usuarios.php         # Gestão de usuários (admin)
│   ├── relatorios.php       # Relatórios e consultas
│   ├── configuracoes.php    # Configurações de modelos
│   └── perfil.php           # Perfil do usuário
│
├── includes/                 # Código compartilhado (não acessível via web)
│   ├── config.php           # Configurações da aplicação
│   ├── database.php         # Conexão PDO (singleton)
│   ├── session.php          # Configuração de sessões
│   ├── auth.php             # Autenticação e autorização
│   └── helpers.php          # Funções utilitárias
│
├── templates/                # Componentes de layout reutilizáveis
│   ├── header.php           # Cabeçalho HTML
│   ├── footer.php           # Rodapé HTML
│   ├── sidebar.php          # Menu lateral
│   ├── topbar.php           # Barra superior
│   ├── mobile-menu.php      # Menu mobile
│   └── theme-resources.php  # Recursos de tema (CSS/JS)
│
├── scripts/                  # Utilitários CLI
│   ├── create_admin.php     # Criação de usuário admin
│   ├── import_equipment.php # Importação em massa (CSV)
│   └── test_db.php          # Testes de conexão
│
├── data/                     # Dados de apoio
│   └── import/              # Arquivos CSV de exemplo
│
├── schema.sql                # Script de criação do banco
├── index.php                 # Bootstrap alternativo (fallback)
└── .htaccess                 # Configurações Apache
```

---

## 4. Arquitetura do Backend

### 4.1 Camadas da Aplicação

```
┌─────────────────────────────────────────────────┐
│           CAMADA DE APRESENTAÇÃO                │
│  (public/*.php - Páginas acessíveis via web)   │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│         CAMADA DE LÓGICA DE NEGÓCIO            │
│  (includes/auth.php, helpers.php)               │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│         CAMADA DE ACESSO A DADOS                 │
│  (includes/database.php - PDO)                  │
└─────────────────┬───────────────────────────────┘
                  │
┌─────────────────▼───────────────────────────────┐
│            BANCO DE DADOS                        │
│  (MySQL/MariaDB - controle_estoque)             │
└─────────────────────────────────────────────────┘
```

### 4.2 Fluxo de Requisição

```
1. Cliente faz requisição HTTP
   ↓
2. Servidor Web (Apache/Nginx) recebe
   ↓
3. PHP processa o arquivo em public/
   ↓
4. Inclusão de includes/ (config, database, auth, helpers)
   ↓
5. Verificação de autenticação (require_login)
   ↓
6. Processamento da lógica da página
   ↓
7. Consultas ao banco via PDO
   ↓
8. Renderização HTML (templates + dados)
   ↓
9. Resposta HTTP enviada ao cliente
```

### 4.3 Componentes Principais do Backend

#### 4.3.1 Sistema de Autenticação (`includes/auth.php`)

**Responsabilidades:**
- Validação de credenciais (email + senha)
- Gerenciamento de sessão
- Controle de acesso baseado em perfis
- Proteção CSRF
- Timeout de sessão

**Funções Principais:**
```php
login($email, $password)           // Autentica usuário
logout()                           // Encerra sessão
require_login($roles)              // Middleware de autenticação
user_has_role($roles)              // Verifica permissões
current_user()                     // Retorna usuário atual
ensure_csrf_token()               // Gera token CSRF
validate_csrf_token($token)        // Valida token CSRF
```

**Fluxo de Autenticação:**
```
1. Usuário submete formulário de login
2. login() valida email e senha (password_verify)
3. Verifica se usuário está ativo (is_active = 1)
4. Atualiza last_login no banco
5. Regenera ID de sessão (session_regenerate_id)
6. Armazena user_id e user_role na sessão
7. Redireciona para dashboard
```

#### 4.3.2 Camada de Dados (`includes/database.php`)

**Padrão:** Singleton (uma única conexão PDO por requisição)

**Características:**
- Conexão persistente via variável estática
- Tratamento de erros com PDOException
- Configuração de charset UTF-8
- Prepared statements habilitados (segurança)

**Exemplo de Uso:**
```php
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => $userId]);
$user = $stmt->fetch();
```

#### 4.3.3 Helpers (`includes/helpers.php`)

**Funções Utilitárias:**
- `sanitize($str)` - Escapa HTML para prevenir XSS
- `flash($key, $message, $type)` - Mensagens flash (sucesso/erro)
- `redirect($url)` - Redirecionamento HTTP
- `format_date($date)` - Formatação de datas
- `format_currency($value)` - Formatação monetária

### 4.4 Perfis e Permissões

| Perfil | Permissões |
|--------|------------|
| **admin** | Acesso total: usuários, equipamentos, movimentações, configurações, exclusões |
| **gestor** | Gestão operacional: equipamentos, movimentações, clientes, modelos |
| **usuario** | Visualização: dashboard, equipamentos (read-only), relatórios, perfil próprio |

**Implementação:**
- Controle via `require_login(['admin', 'gestor'])` no início de cada página
- Verificação adicional com `user_has_role()` para ações específicas
- Validação no banco: campo `role` ENUM('admin','gestor','usuario')

---

## 5. Arquitetura do Frontend

### 5.1 Estrutura de Renderização

```
┌─────────────────────────────────────────────┐
│  templates/header.php                      │
│  - Meta tags, links CSS                    │
│  - theme-resources.php (Tailwind, Alpine)  │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│  templates/topbar.php                      │
│  - Barra superior com navegação             │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│  templates/sidebar.php                     │
│  - Menu lateral (desktop)                  │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│  public/[página].php                      │
│  - Conteúdo principal da página            │
│  - Lógica PHP + HTML                      │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│  templates/footer.php                      │
│  - Scripts JavaScript                      │
│  - Fechamento HTML                         │
└─────────────────────────────────────────────┘
```

### 5.2 Sistema de Temas

**Implementação:**
- Armazenamento: `localStorage` (chave: `ui-theme`)
- Valores: `'light'` ou `'dark'`
- Detecção automática: `prefers-color-scheme` (fallback)
- Aplicação: atributo `data-theme` no elemento `<html>`

**Arquivo:** `templates/theme-resources.php`

**Componentes:**
1. **UITheme API** (JavaScript vanilla)
   - `UITheme.get()` - Obtém tema atual
   - `UITheme.set(theme)` - Define tema
   - `UITheme.toggle()` - Alterna tema

2. **Alpine.js Store**
   - Store `uiTheme` sincronizado com UITheme API
   - Reatividade para componentes Alpine

3. **CSS Variables**
   - Variáveis CSS customizadas por tema
   - Classes utilitárias Tailwind com suporte a dark mode

### 5.3 Interatividade (Alpine.js)

**Uso Principal:**
- Formulários dinâmicos (mostrar/ocultar campos)
- Toggle de menus mobile
- Validação client-side
- Estados de loading em botões

**Exemplo:**
```html
<div x-data="{ showAdvanced: false }">
  <button @click="showAdvanced = !showAdvanced">Avançado</button>
  <div x-show="showAdvanced">Campos avançados...</div>
</div>
```

### 5.4 Visualizações (Chart.js)

**Uso:**
- Dashboard operacional (`dashboard.php`)
- Dashboard administrativo (`admin_dashboard.php`)

**Gráficos:**
- Tendência de saídas (12 meses)
- Distribuição geográfica (por estado)
- Condição dos equipamentos (pie chart)
- Operações recentes (timeline)

---

## 6. Arquitetura do Banco de Dados

### 6.1 Modelo de Dados

```
┌─────────────┐
│   users     │
│─────────────│
│ id (PK)     │
│ name        │
│ email (UK)  │
│ password_   │
│   hash      │
│ role        │
│ is_active   │
└──────┬──────┘
       │
       │ created_by / updated_by
       │
┌──────▼──────────────────────────────────┐
│            equipment                     │
│──────────────────────────────────────────│
│ id (PK)                                  │
│ asset_tag (UK)                           │
│ model_id (FK → equipment_models)        │
│ serial_number                            │
│ mac_address                              │
│ condition_status                         │
│ status                                   │
│ current_client_id (FK → clients)        │
│ created_by (FK → users)                 │
│ updated_by (FK → users)                 │
└──────┬───────────────────────────────────┘
       │
       │ equipment_id
       │
┌──────▼──────────────────────────────────┐
│   equipment_operation_items              │
│──────────────────────────────────────────│
│ id (PK)                                  │
│ operation_id (FK → equipment_operations)│
│ equipment_id (FK → equipment)            │
│ accessories_power                        │
│ accessories_hdmi                         │
│ accessories_remote                        │
│ condition_after_return                   │
└──────┬───────────────────────────────────┘
       │
       │ operation_id
       │
┌──────▼──────────────────────────────────┐
│      equipment_operations               │
│──────────────────────────────────────────│
│ id (PK)                                  │
│ operation_type (ENTRADA/SAIDA/RETORNO)  │
│ operation_date                           │
│ client_id (FK → clients)                │
│ performed_by (FK → users)               │
│ notes                                    │
└──────────────────────────────────────────┘

┌─────────────┐      ┌──────────────────┐
│   clients   │      │ equipment_models │
│─────────────│      │──────────────────│
│ id (PK)     │      │ id (PK)          │
│ client_code │      │ category         │
│ name        │      │ brand            │
│ cnpj        │      │ model_name       │
│ ...         │      │ monitor_size     │
└─────────────┘      │ is_active        │
                     └──────────────────┘

┌──────────────────┐
│ equipment_notes  │
│──────────────────│
│ id (PK)          │
│ equipment_id (FK)│
│ user_id (FK)     │
│ note             │
│ created_at       │
└──────────────────┘
```

### 6.2 Tabelas Principais

#### `users`
Armazena usuários do sistema com autenticação e autorização.

**Campos Chave:**
- `role`: ENUM('admin','gestor','usuario')
- `password_hash`: Hash Bcrypt da senha
- `is_active`: Flag de ativação

#### `equipment`
Inventário físico de equipamentos.

**Campos Chave:**
- `status`: ENUM('em_estoque','alocado','manutencao','baixado')
- `condition_status`: ENUM('novo','usado')
- `current_client_id`: Cliente atual (quando alocado)

#### `equipment_operations`
Cabeçalho de movimentações (entrada, saída, retorno).

**Campos Chave:**
- `operation_type`: ENUM('ENTRADA','SAIDA','RETORNO')
- `performed_by`: Usuário responsável pela operação

#### `equipment_operation_items`
Itens vinculados a uma operação (relação N:N entre operações e equipamentos).

**Campos Chave:**
- `accessories_*`: Flags de acessórios recebidos
- `condition_after_return`: Condição após retorno

### 6.3 Relacionamentos

| Relacionamento | Tipo | Descrição |
|----------------|------|-----------|
| `equipment.model_id` → `equipment_models.id` | N:1 | Cada equipamento tem um modelo |
| `equipment.current_client_id` → `clients.id` | N:1 | Equipamento pode estar alocado a um cliente |
| `equipment_operations.client_id` → `clients.id` | N:1 | Operação pode estar vinculada a um cliente |
| `equipment_operations.performed_by` → `users.id` | N:1 | Operação realizada por um usuário |
| `equipment_operation_items.operation_id` → `equipment_operations.id` | N:1 | Item pertence a uma operação (CASCADE DELETE) |
| `equipment_operation_items.equipment_id` → `equipment.id` | N:1 | Item referencia um equipamento |

---

## 7. Fluxos de Dados

### 7.1 Fluxo de Entrada de Equipamento

```
1. Usuário (admin/gestor) acessa entrada_cadastrar.php
   ↓
2. Preenche formulário (modelo, série, MAC, condição, etc.)
   ↓
3. Submete POST com CSRF token
   ↓
4. Validações:
   - Modelo existe e está ativo
   - MAC válido (formato normalizado)
   - Série obrigatória
   ↓
5. Transação no banco:
   a) INSERT INTO equipment
   b) INSERT INTO equipment_operations (tipo: ENTRADA)
   c) INSERT INTO equipment_operation_items
   d) INSERT INTO equipment_notes (dados técnicos)
   ↓
6. Commit da transação
   ↓
7. Flash message de sucesso
   ↓
8. Redirecionamento para equipamentos.php
```

### 7.2 Fluxo de Saída de Equipamento

```
1. Usuário (admin/gestor) acessa saida_registrar.php
   ↓
2. Sistema lista equipamentos com status='em_estoque'
   ↓
3. Usuário seleciona múltiplos equipamentos
   ↓
4. Seleciona ou cria cliente
   ↓
5. Submete POST com CSRF token
   ↓
6. Validações:
   - Todos os equipamentos ainda estão em estoque
   - Cliente existe ou código único
   ↓
7. Transação no banco:
   a) INSERT INTO equipment_operations (tipo: SAIDA)
   b) INSERT INTO equipment_operation_items (para cada equipamento)
   c) UPDATE equipment SET status='alocado', current_client_id=?
   ↓
8. Commit da transação
   ↓
9. Flash message de sucesso
   ↓
10. Redirecionamento para equipamentos.php
```

### 7.3 Fluxo de Retorno de Equipamento

```
1. Usuário (admin/gestor) acessa retornos.php
   ↓
2. Sistema lista equipamentos com status='alocado' agrupados por cliente
   ↓
3. Usuário seleciona equipamentos do mesmo cliente
   ↓
4. Informa acessórios recebidos e condição pós-retorno
   ↓
5. Submete POST com CSRF token
   ↓
6. Validações:
   - Todos os equipamentos pertencem ao mesmo cliente
   - Todos ainda estão alocados
   ↓
7. Transação no banco:
   a) INSERT INTO equipment_operations (tipo: RETORNO)
   b) INSERT INTO equipment_operation_items (com acessórios e condição)
   c) UPDATE equipment SET:
      - condition_status='usado'
      - status=? (baseado em condition_after_return)
      - current_client_id=NULL
   ↓
8. Commit da transação
   ↓
9. Flash message de sucesso
   ↓
10. Redirecionamento para equipamentos.php
```

---

## 8. Segurança

### 8.1 Autenticação

- **Senhas**: Hash Bcrypt (algoritmo seguro, custo padrão)
- **Sessões**: Regeneração de ID após login (`session_regenerate_id`)
- **Timeout**: Suporte a timeout por inatividade (`SESSION_IDLE_TIMEOUT`)
- **Cookies**: Configuração segura (SameSite=Lax, HttpOnly quando HTTPS)

### 8.2 Autorização

- **Controle de Acesso**: Middleware `require_login()` em todas as páginas protegidas
- **Verificação de Perfil**: Validação de `role` antes de ações sensíveis
- **Self-Protection**: Usuário não pode excluir a si mesmo

### 8.3 Proteção contra Ataques

| Ataque | Proteção |
|--------|----------|
| **SQL Injection** | Prepared statements (PDO) |
| **XSS** | Função `sanitize()` para escape de HTML |
| **CSRF** | Tokens CSRF em todos os formulários POST |
| **Session Hijacking** | Regeneração de ID de sessão após login |
| **Brute Force** | Não implementado (recomendado: rate limiting) |

### 8.4 Validação de Dados

- **Server-side**: Validação em PHP antes de persistir
- **Client-side**: Validação HTML5 e JavaScript (UX, não segurança)
- **Banco de Dados**: Constraints (UNIQUE, FOREIGN KEY, ENUM)

---

## 9. Padrões e Convenções

### 9.1 Estrutura de Páginas

Todas as páginas protegidas seguem o padrão:

```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Autenticação e autorização
require_login(['admin', 'gestor']); // ou roles permitidas

// Processamento de formulários (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validação CSRF
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        flash('error', 'Token inválido.');
        redirect('pagina.php');
    }
    
    // Processamento...
}

// Consultas ao banco
$pdo = get_pdo();
// ...

// Inclusão de templates
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/topbar.php';
require_once __DIR__ . '/../templates/sidebar.php';

// Conteúdo da página
?>

<main>
    <!-- HTML da página -->
</main>

<?php
require_once __DIR__ . '/../templates/footer.php';
?>
```

### 9.2 Nomenclatura

- **Arquivos**: snake_case (ex: `entrada_cadastrar.php`)
- **Funções**: snake_case (ex: `require_login()`)
- **Variáveis**: camelCase ou snake_case (convenção mista)
- **Tabelas**: snake_case (ex: `equipment_operations`)
- **Colunas**: snake_case (ex: `current_client_id`)

### 9.3 Tratamento de Erros

- **PDO Exceptions**: Capturadas e exibidas via `templates/error.php`
- **Validações**: Flash messages para feedback ao usuário
- **Logs**: `error_log()` para erros críticos

---

## 10. Diagramas de Arquitetura

### 10.1 Arquitetura Geral

```
                    ┌──────────────┐
                    │   Cliente    │
                    │  (Navegador) │
                    └──────┬───────┘
                           │ HTTP/HTTPS
                           │
                    ┌──────▼──────────────┐
                    │   Servidor Web      │
                    │  (Apache/Nginx)     │
                    └──────┬──────────────┘
                           │
                    ┌──────▼──────────────┐
                    │   PHP Interpreter   │
                    │  (PHP 8.1+)        │
                    └──────┬──────────────┘
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
┌───────▼──────┐  ┌────────▼────────┐  ┌──────▼──────┐
│   public/    │  │   includes/     │  │ templates/  │
│  (Rotas)     │  │  (Lógica)      │  │  (Layout)   │
└───────┬──────┘  └────────┬────────┘  └─────────────┘
        │                  │
        └──────────┬───────┘
                   │
            ┌──────▼────────┐
            │  database.php  │
            │     (PDO)     │
            └──────┬────────┘
                   │
            ┌──────▼────────┐
            │   MySQL/      │
            │   MariaDB     │
            └───────────────┘
```

### 10.2 Fluxo de Autenticação

```
┌─────────┐
│ Usuário │
└────┬────┘
     │ 1. Acessa login.php
     ▼
┌─────────────────┐
│  Formulário     │
│  de Login       │
└────┬────────────┘
     │ 2. Submete credenciais
     ▼
┌─────────────────┐
│  auth.php       │
│  login()        │
└────┬────────────┘
     │ 3. Valida email/senha
     │    (password_verify)
     ▼
┌─────────────────┐
│  Banco de Dados │
│  (users table)  │
└────┬────────────┘
     │ 4. Retorna usuário
     ▼
┌─────────────────┐
│  Sessão PHP      │
│  $_SESSION       │
│  - user_id       │
│  - user_role     │
└────┬────────────┘
     │ 5. Redireciona
     ▼
┌─────────────────┐
│  dashboard.php  │
│  (require_login)│
└─────────────────┘
```

### 10.3 Fluxo de Operação (Entrada/Saída/Retorno)

```
┌──────────────────┐
│  Página de       │
│  Operação        │
│  (entrada/saída/ │
│   retorno)       │
└────────┬─────────┘
         │
         │ 1. Usuário preenche formulário
         ▼
┌──────────────────┐
│  Validação       │
│  - CSRF token    │
│  - Dados         │
│  - Permissões    │
└────────┬─────────┘
         │
         │ 2. Inicia transação
         ▼
┌──────────────────┐
│  INSERT/UPDATE   │
│  - equipment     │
│  - operations    │
│  - items         │
│  - notes         │
└────────┬─────────┘
         │
         │ 3. Commit
         ▼
┌──────────────────┐
│  Flash Message   │
│  (Sucesso/Erro)  │
└────────┬─────────┘
         │
         │ 4. Redireciona
         ▼
┌──────────────────┐
│  Página de       │
│  Listagem        │
│  (equipamentos)  │
└──────────────────┘
```

---

## 11. Considerações de Performance

### 11.1 Otimizações Implementadas

- **Conexão PDO Singleton**: Uma única conexão por requisição
- **Prepared Statements**: Reutilização de queries preparadas
- **Índices no Banco**: Chaves primárias e foreign keys
- **Cache de Usuário**: `current_user()` com cache estático

### 11.2 Pontos de Atenção

- **N+1 Queries**: Algumas listagens podem gerar múltiplas queries (considerar JOINs)
- **Sessões em Arquivo**: Em produção, considerar Redis/Memcached
- **Assets via CDN**: Tailwind e Alpine carregados via CDN (dependência externa)

---

## 12. Extensibilidade

### 12.1 Pontos de Extensão

1. **Novos Módulos**: Adicionar páginas em `public/` seguindo o padrão existente
2. **Novos Helpers**: Adicionar funções em `includes/helpers.php`
3. **Novos Templates**: Criar componentes reutilizáveis em `templates/`
4. **Novos Perfis**: Adicionar valores ao ENUM `users.role` e atualizar `require_login()`

### 12.2 Melhorias Futuras Sugeridas

- **API REST**: Separar backend em API e frontend SPA
- **Cache**: Implementar cache de queries frequentes
- **Logs de Auditoria**: Tabela dedicada para rastreamento de ações
- **Exportação**: CSV/PDF para relatórios
- **Notificações**: Sistema de alertas (email/push)

---

## 13. Referências

- **Documentação Técnica**: `MAPEAMENTO_SISTEMA.md`
- **Requisitos**: `PRD_COMPLETO.md`
- **Setup**: `README.md`
- **Deploy**: `README_DEPLOY.md`
- **Schema**: `schema.sql`

---

**Versão do Documento**: 1.0  
**Última Atualização**: Janeiro 2026  
**Autor**: Sistema de Documentação Automática
