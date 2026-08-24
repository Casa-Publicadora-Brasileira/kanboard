---
name: Kanboard Page Creator
description: Diretrizes e regras exatas para evoluir o projeto Kanboard criando novas páginas sem quebrar a estrutura MVC nativa.
---
# Kanboard Page Creator

Esta skill define as regras e diretrizes para criar novas páginas e manter o padrão arquitetural MVC nativo do Kanboard. O Kanboard não utiliza frameworks modernos complexos para o frontend, mantendo uma abordagem PHP clássica. 

Para evoluir o projeto sem quebrar a estrutura, siga estritamente os passos abaixo.

## 1. Mapeamento de Rotas (Router)

As rotas no Kanboard (núcleo) são definidas primariamente no arquivo `app/ServiceProvider/RouteProvider.php`.
*(Se estiver desenvolvendo um plugin, as rotas são injetadas no `Plugin.php` utilizando `$this->route->addRoute(...)`).*

**Diretrizes:**
- Adicione novas rotas dentro do método `register` em `app/ServiceProvider/RouteProvider.php` usando:
  `$container['route']->addRoute('caminho/da/url', 'NomeDoController', 'nomeDoMetodo');`
- Se for precisar de parâmetros: `$container['route']->addRoute('caminho/:id', 'NomeDoController', 'nomeDoMetodo');`
- Mantenha URLs semânticas e consistentes com o resto do sistema (preferencialmente em minúsculas, usando hífens para espaços).

## 2. Controladores (Controllers)

Os Controllers devem ser criados no diretório `app/Controller/` e obrigatoriamente estender a classe `BaseController`.

**Diretrizes:**
- **Namespace:** `namespace Kanboard\Controller;`
- **Herança:** `class MeuNovoController extends BaseController`
- **Lógica Mínima:** Evite regras de negócios densas no Controller. O Controller serve apenas para orquestrar o fluxo:
  1. Processar entradas (parâmetros da URL, formulários, POST/GET).
  2. Verificar permissões, se aplicável.
  3. Recuperar ou manipular dados através dos Models.
  4. Renderizar a view injetando as variáveis correspondentes.
- **Renderização da View:**
  Utilize o helper de layout para envelopar a página dentro da interface padrão do Kanboard.
  Exemplo:
  ```php
  public function show()
  {
      $this->response->html($this->helper->layout->app('meu_diretorio/meu_template', array(
          'title' => t('Título da Página'),
          'minha_variavel' => 'Meu valor',
      )));
  }
  ```
  *(Nota: O método `app()` do `LayoutHelper` é comum para a maioria das páginas genéricas, mas há também `dashboard()`, `project()`, `config()`, dependendo do contexto da interface).*

## 3. Templates (Views)

As views (templates) estão localizadas no diretório `app/Template/`. O Kanboard renderiza arquivos PHP puros como views.

**Diretrizes:**
- Crie um novo subdiretório em `app/Template/` se a funcionalidade for um módulo novo (ex: `app/Template/meu_modulo/index.php`), ou utilize um existente que faça sentido lógico.
- **Sintaxe:** Utilize PHP misturado com HTML (`<?= $minha_variavel ?>` ou `<?php if (...): ?> ... <?php endif ?>`).
- **Tradução:** Sempre envolva strings visíveis ao usuário na função de tradução `t()`. Ex: `<?= t('Meu Texto') ?>`.
- **Prevenção de XSS:** Se exibir dados dinâmicos do usuário ou banco de dados, certifique-se de escapar o HTML. Utilize o helper do Kanboard, ex: `$this->text->e($variavel_insegura)`.
- **Helpers de UI e Links:** Use os ajudantes (helpers) disponíveis globalmente nas views via `$this->url`.
  Exemplo de criação de Link: `<?= $this->url->link(t('Clique aqui'), 'MeuNovoController', 'show', array('param' => 1)) ?>`

## 4. Models (Regras de Negócio)

Se a nova página requerer manipulação ou exibição de dados complexos:
- Utilize ou crie Models no diretório `app/Model/`.
- Models estendem a classe base `Base` (que fornece acesso facilitado ao DB).
- O Kanboard utiliza a biblioteca PicoDb. As queries são feitas através do query builder nativo, por exemplo: `$this->db->table('minha_tabela')->eq('id', 1)->findAll()`.

## 5. Resumo do Fluxo (Checklist MVC do Kanboard)

1. [ ] **Rota:** Adicione a definição em `app/ServiceProvider/RouteProvider.php` (ou arquivo equivalente no contexto de plugins).
2. [ ] **Model (Opcional):** Se precisar de lógicas novas de DB, crie/modifique em `app/Model/`.
3. [ ] **Controller:** Crie o arquivo em `app/Controller/` com o `namespace` correto e estendendo `BaseController`. Programe o método vinculado à rota.
4. [ ] **Template:** Crie o arquivo `.php` em `app/Template/`. Iniba XSS e use `t()` para internacionalização.
5. [ ] **Testes Manuais:** Navegue até a URL, confira erros no console, teste se a passagem de variáveis (`array(...)` no Controller) chega corretamente no View.

Seguir este padrão rigorosamente garantirá que o seu Kanboard seja customizado ou evoluído de forma alinhada ao "Kanboard Way", evitando dívidas técnicas e facilitando atualizações futuras.
