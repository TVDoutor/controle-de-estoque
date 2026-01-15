# Sistema de Controle de Estoque

Aplicação web em PHP + MySQL para controle de estoque de boxes Android e monitores, com autenticação baseada em perfis (admin, gestor, usuário) e interface em Tailwind CSS.

## Requisitos

- PHP 8.1 ou superior com extensões `pdo_mysql`, `openssl`, `mbstring`.
- Servidor HTTP (ex.: Apache ou Nginx) configurado para apontar o diretório `public/` como raiz.
- MySQL 5.7+/MariaDB 10.4+.

## Configuração inicial

1. **Banco de dados**
   - Importe o arquivo `schema.sql` no servidor MySQL definido em `includes/config.php`. O script cria a base `controle_estoque` e todas as tabelas necessárias.
   - Os modelos de equipamentos padrão são inseridos automaticamente pelo script.

2. **Credenciais de acesso ao banco**
   - Ajuste `includes/config.php` conforme o ambiente alvo (host, usuário, senha, nome do banco). O repositório já está configurado com os dados informados (HostGator).

3. **Usuário administrador**
   - Execute no terminal: `php scripts/create_admin.php "Nome" email@dominio.com senha_segura`
   - O script cria um usuário com perfil `admin`.

4. **Configuração do servidor web**
   - Aponte o DocumentRoot para `public/`.
   - Certifique-se de que as sessões PHP estão habilitadas e o diretório de sessão possui permissão de escrita.

## Perfis e permissões

- **Admin**: gerencia usuários, modelos de equipamento e possui todas as permissões operacionais.
- **Gestor**: gerencia equipamentos, movimentações, clientes e modelos.
- **Usuário**: visualiza relatórios e estoque, além de editar o próprio perfil.

## Funcionalidades

- Autenticação com controle de sessão e CSRF em formulários críticos.
- Cadastro e edição de usuários (apenas admin) e perfil pessoal.
- Cadastro de equipamentos com registro automático de entrada.
- Registro de saídas para clientes com suporte a envio múltiplo de equipamentos.
- Registro de retornos com controle de acessórios, condição pós-retorno e atualização do estoque (novo/usado/manutenção/descartar).
- Cadastro e edição de clientes.
- Cadastro de modelos de equipamentos (Android box, monitores, outros).
- Dashboard com estatísticas, indicadores e histórico recente.
- Relatórios filtráveis por período/tipo de operação.

## Estrutura de diretórios

```
/ (raiz)
├── public/             # Arquivos acessíveis via web (login, dashboard, páginas internas)
├── includes/           # Configuração, conexão e helpers
├── templates/          # Layout compartilhado (header, sidebar, topbar, footer)
├── assets/             # Espaço reservado para arquivos estáticos
├── scripts/            # Utilitários (ex.: criação de admin)
└── schema.sql          # Script do banco de dados
```

## Observações

- O layout utiliza Tailwind via CDN, sem build step adicional.
- Para ambientes de produção, considere mover as credenciais para variáveis de ambiente e forçar HTTPS.
- Não há dependências externas além do PHP padrão.
- Testes automatizados não foram adicionados; recomenda-se validar as principais operações após configurar o banco.

## Próximos passos sugeridos

1. Habilitar logs de auditoria ou exportação CSV dos relatórios.
2. Adicionar filtros avançados à listagem de equipamentos (status por cliente, intervalo de datas).
3. Criar mecanismo de anexos (ex.: comprovantes) em movimentações, se necessário.
4. Preparar script de implantação (deploy) para HostGator e agendar backups automáticos do banco.
## Importação em massa de equipamentos

O repositório inclui o utilitário `php scripts/import_equipment.php` para cadastrar lotes de equipamentos a partir de planilhas CSV.

1. Prepare o arquivo em UTF-8 com cabeçalho contendo, no mínimo, as colunas:
   - `Dados do Cliente Alocado`
   - `ID do Player`
   - `ID Legado do Player`
   - `Modelo do Aparelho`
   - `Número de Série`
   - `Endereço MAC`
   - `Equipamento desvinculado`
   - As demais colunas (`Versão do OS`, `Versão do App`, `Localização Lat Long`) são opcionais, mas recomendadas.
2. Faça um teste de simulação (nada é gravado):
   ```bash
   php scripts/import_equipment.php --dry-run data/import/equipment_import_sample.csv
   ```
   Revise os avisos de campos ausentes, MAC inválido ou modelos não encontrados.
3. Execute a importação definitiva (sem `--dry-run`) após os ajustes:
   ```bash
   php scripts/import_equipment.php data/minha_planilha.csv
   ```

O script cria clientes e modelos inexistentes, normaliza MACs, vincula o equipamento ao cliente e registra notas técnicas. As inclusões são registradas como operações de entrada e cada linha roda em transação própria. Um CSV de exemplo está em `data/import/equipment_import_sample.csv`.
