---
description: Memória e contexto do projeto Kanboard customizado.
---
# Contexto do Projeto Kanboard (Memória)

Este arquivo documenta as configurações específicas e arquitetura deste fork/customização do Kanboard para orientar as ações futuras no repositório.

## 1. Stack Tecnológico e Ferramentas

- **Backend:** PHP puro usando a estrutura MVC nativa do Kanboard (sem frameworks complexos).
- **Banco de Dados:** MariaDB via Docker (configurado em `docker-compose.mysql.yml`).
- **Frontend / Estilização:**
  - **Tailwind CSS v4:** Ao contrário do Kanboard padrão, este projeto utiliza o Tailwind CSS v4 para estilização.
  - A build do Tailwind é feita via NPM script: `npm run tw:watch` (desenvolvimento) ou `npm run tw:build`.
  - O arquivo de entrada do CSS é `./assets/css/src/tailwind.css` e o output gerado e minificado fica em `./assets/css/tailwind.min.css`.

## 2. Padrões de Desenvolvimento

- **Criação de Páginas:** Sempre siga a skill `Kanboard Page Creator` para criar novas páginas. Mantenha as regras de negócio no Model, controle de fluxo no Controller e apresentação nas Views (`app/Template/`).
- **Tradução:** O sistema utiliza a função `t()` para strings na view. Sempre traduza os textos da interface.
- **Segurança (XSS):** Faça o escape de todo input dinâmico usando `$this->text->e(...)` nas Views.

## 3. Ambiente de Desenvolvimento Local

Geralmente, o ambiente de desenvolvimento opera com dois processos rodando simultaneamente em background:

1. **Servidor PHP embutido:** `php -S localhost:8000 -t .`
2. **Watch do Tailwind CSS:** `npm run tw:watch`

Para o banco de dados, certifique-se de que o container do MariaDB (`docker-compose -f docker-compose.mysql.yml up -d`) esteja ativo caso haja manipulação do DB local.

## 4. Estrutura de Diretórios de Customizações (.agents)

- `.agents/skills/kanboard-page-creator/`: Contém as instruções detalhadas (SKILL.md) de como implementar corretamente a arquitetura MVC no Kanboard. Consulte sempre ao adicionar telas ou novas funcionalidades.
