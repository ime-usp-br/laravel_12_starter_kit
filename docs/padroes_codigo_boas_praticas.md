# Padrões de Código e Boas Práticas

**Versão:** 0.1.0<br>
**Data:** 2025-05-29

Manter um código limpo, consistente e fácil de entender é crucial para a manutenabilidade e evolução de qualquer projeto. Este starter kit adota e **REQUER** a aderência às seguintes práticas e padrões:

## Padrão de Codificação: PSR-12 e Laravel Pint

Todo o código PHP **DEVE** seguir o [PSR-12: Extended Coding Style Guide](https://www.php-fig.org/psr/psr-12/). O starter kit vem configurado com [Laravel Pint](https://laravel.com/docs/12.x/pint) usando o preset `laravel`, que implementa o PSR-12 com algumas convenções adicionais do Laravel.

*   **Execução:** Formate seu código automaticamente antes de commitar:
    ```bash
    ./vendor/bin/pint
    ```
*   **Verificação (CI):** A Integração Contínua (se configurada) **DEVE** incluir um passo para verificar a formatação:
    ```bash
    ./vendor/bin/pint --test
    ```
*   **Principais Regras (Reforço):**
    *   Indentação: 4 espaços, **NÃO** tabs.
    *   Limite de Linha: ~120 caracteres (flexível, mas evite linhas excessivamente longas).
    *   Final de Linha/Arquivo: Sem espaços em branco no final das linhas; uma linha em branco no final do arquivo.
    *   Tags PHP: Omitir `?>` em arquivos só PHP; usar `<?php` ou `<?=`.
    *   Namespaces e `use`: Seguir PSR-12 (uma classe/interface/trait por declaração, ordenação).
    *   Classes, Propriedades, Métodos: Visibilidade explícita, ordem correta, chaves `{}` em nova linha para classes/métodos.
*   **Comentários:** Remova comentários desnecessários, preferindo nomes descritivos. Use comentários para explicar o *porquê* de decisões complexas ou para gerar documentação (DocBlocks).

## Boas Práticas Laravel / USP

Além da formatação, siga estas convenções e práticas recomendadas:

### Nomenclatura (Consistente)

| Recurso             | Convenção        | Exemplo Bom                      | Exemplo Ruim                     | Notas                                         |
| :------------------ | :--------------- | :------------------------------- | :------------------------------- | :-------------------------------------------- |
| Controller          | Singular         | `ProductController`, `UserProfileController` | `ProductsController`             | Suffix `Controller` obrigatório.              |
| Model               | Singular         | `Product`, `User`, `OrderLine`   | `Products`, `Users`              |                                               |
| Propriedade Model   | `snake_case`     | `$user->created_at`, `$order->line_total` | `$user->createdAt`             | Padrão Eloquent.                              |
| Views (arquivo)     | **`kebab-case`** | `show-filtered.blade.php`, `edit-user-profile.blade.php` | `showFiltered.blade.php`, `edit_user_profile.blade.php` | **Convenção Adotada.** (Pode ser verificada no futuro). |
| Views (pasta)       | `kebab-case` ou `snake_case` | `user-profiles/`, `product-orders/` | `userProfiles/`                  | Plural ou descritivo. Mantenha consistência. |
| Componente Blade    | `kebab-case`     | `<x-usp.header />`, `<x-form.input />` | `<x-UspHeader />`                | Prefixo opcional para namespace.              |
| Componente Livewire | `kebab-case`     | `show-posts`, `user-profile-form` | `ShowPosts`, `UserProfileForm` | Tag: `<livewire:show-posts />`              |
| Métodos             | `camelCase`      | `getPrice()`, `getAllActiveUsers()` | `get_price()`, `getallactiveusers()` | Verbos descritivos.                           |
| Métodos Controller  | Verbo padrão     | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` | `listProducts`, `saveProduct`    | Use verbos específicos para ações não-CRUD. |
| Métodos Teste       | `camelCase` ou `snake_case` | `test_guest_cannot_see_admin_page()`, `it_calculates_the_correct_total()` | `testAdminAccess`                | Descritivo, indicando o que testa. (Pest usa snake_case por padrão). |
| Rotas (URI)         | `kebab-case`, Plural | `/user-profiles/{profile}`, `/product-orders` | `/userProfiles/{profile}`      |                                               |
| Rotas (Nome)        | `dot.notation`   | `admin.users.index`, `products.show`, `locale.set` | `admin-users-index`, `showProduct` | Agrupado por recurso/área.                    |
| Tabelas             | `snake_case`, Plural | `users`, `product_orders`, `permission_user` | `User`, `ProductOrders`          |                                               |
| Colunas Tabela      | `snake_case`     | `first_name`, `order_total`, `user_id` | `firstName`, `ordertotal`        | Incluindo PK (`id`) e FKs (`related_model_id`). |
| Tabela Pivot        | `snake_case`, Singular, Ordem Alfabética | `permission_user`, `product_tag` | `user_permissions`, `products_tags` |                                            |
| Variáveis           | `camelCase`      | `$activeUsers`, `$orderTotal`, `$userProfile` | `$active_users`, `$ordertotal`   | Descritivo.                                   |
| Variável (Collection)| Descritivo, Plural | `$activeUsers = User::active()->get();` | `$users = User::get();`          | Indicar o conteúdo.                           |
| Variável (Object)   | Descritivo, Singular | `$user = User::find(1);`         | `$users = User::find(1);`        | Indicar o tipo.                               |
| Config/Linguagem    | `snake_case`     | `config('services.senhaunica.key')`, `__('auth.failed')` | `config('services.senhaUnica.key')` | Chaves de array/arquivos.                     |
| Contract (Interface)| Adjetivo/Substantivo | `Authenticatable`, `ShouldQueue`, `UserRepository` | `IUserRepo`, `UserInterface`     |                                               |
| Trait               | Adjetivo         | `Notifiable`, `HasRoles`, `SoftDeletes` | `NotificationTrait`              | Sufixo `Trait` desnecessário.                 |
| Arquivos Tradução (JSON) | Código Locale (ISO) | `en.json`, `pt_BR.json`, `es.json` | `English.json`, `pt-br.json`   | Padrão `laravel-lang/lang`.                    |
| Chaves Tradução (JSON) | Texto Padrão | `"Login": "Login"`, `"Hello :name": "Hello :name"` | `"login_button": "Login"`   | Usar o texto no idioma padrão (geralmente inglês) como chave. |

### Princípios Arquiteturais

*   **Princípio da Responsabilidade Única (SRP):** Classes (Controllers, Services, Models, etc.) e métodos **DEVEM** ter uma única responsabilidade bem definida. Evite classes/métodos "faz-tudo". Se um Controller está ficando muito grande, considere usar Actions ou Services.
*   **Controllers Finos:** Controllers **DEVEM** ser responsáveis apenas por:
    1.  Receber a requisição HTTP (`Request` ou `FormRequest`).
    2.  Delegar a lógica de negócio para outra camada (Service, Action, Model, Job).
    3.  Retornar a resposta HTTP (View, Redirect, JSON).
    **NÃO DEVEM** conter lógica de negócio complexa, consultas Eloquent elaboradas, manipulação de dados extensa, ou geração de texto voltado ao usuário (use `__()` nas Views ou passe dados pré-formatados).
*   **Lógica de Negócio em Services/Actions:** Encapsule a lógica de negócio reutilizável ou complexa em classes dedicadas (`app/Services/` ou `app/Actions/`). Services são geralmente injetados nos Controllers. Actions podem ser classes menores e focadas para uma única tarefa.
*   **Validação em Form Requests:** **NÃO DEVE** validar dados (`$request->validate([...])`) diretamente no Controller. Use [Form Requests](filtered_laravel_docs#form-request-validation) (`php artisan make:request`) para encapsular regras de validação e lógica de autorização (`authorize()` method). Mensagens de erro customizadas **DEVEM** ser definidas no Form Request (método `messages()`) e usar `__()` se necessário.
*   **DRY (Don't Repeat Yourself):** Evite duplicação de código. Use:
    *   Métodos privados/protegidos dentro da classe.
    *   Traits para lógica compartilhada entre classes não relacionadas hierarquicamente.
    *   Classes Service/Action.
    *   [Query Scopes Eloquent](filtered_laravel_docs#query-scopes) para consultas reutilizáveis.
    *   Componentes Blade/Livewire para UI reutilizável.
    *   Chaves de tradução (`__()`) para texto reutilizável.
*   **Injeção de Dependência:** Prefira injetar dependências (Services, Repositories, etc.) via construtor do Controller ou método, em vez de usar Facades dentro dos métodos ou instanciar com `new`. Isso facilita os testes (mocking) e o desacoplamento.

### Localização

A aplicação Laravel 12 USP Starter Kit inclui um middleware para detecção automática do locale do navegador, aprimorando a experiência do usuário.

*   **Detecção Automática de Locale (`DetectBrowserLanguageMiddleware`):**
    *   Este middleware, registrado no grupo `web`, define o locale da aplicação (`App::setLocale()`) com base no cabeçalho `Accept-Language` da requisição HTTP.
    *   Ele prioriza o locale que já pode estar definido na sessão do usuário. Se nenhum locale estiver na sessão, tenta mapear o idioma preferencial do navegador para um dos `supported_locales` configurados.
    *   Se o idioma do navegador não for suportado, ou se a detecção falhar, o `config('app.fallback_locale')` será utilizado.
    *   O locale determinado (seja por sessão, detecção do navegador ou fallback) é armazenado na sessão (`Session::put('locale', ...)`) para persistir durante a navegação.
    *   **Configuração de Locales Suportados:** Defina a lista de idiomas que sua aplicação suporta na chave `supported_locales` dentro de `config/app.php`. Por exemplo:
        ```php
        'supported_locales' => [
            'en' => 'English',
            'pt_BR' => 'Português (Brasil)',
            // 'es' => 'Español',
        ],
        ```
    *   A prioridade de aplicação do locale é: **Sessão > Idioma do Navegador > Fallback Padrão.**
*   **Tradução Mandatória:** Todo texto voltado ao usuário **DEVE** usar a função helper `__()`. Isso inclui texto em views Blade, mensagens de erro de validação, mensagens flash, títulos de página, labels de formulário, placeholders, texto de botões, e mensagens em respostas JSON ou logs destinados a usuários/administradores.
    ```php
    // Controller
    return redirect('/')->with('status', __('Profile updated successfully!'));

    // Blade View
    <label for="email">{{ __('Email Address') }}</label>
    <button type="submit">{{ __('Submit') }}</button>
    ```
*   **Arquivos de Tradução:**
    *   **Preferência por JSON:** Para textos específicos da aplicação, **DEVE-SE** preferencialmente usar arquivos JSON (ex: `lang/en.json`, `lang/pt_BR.json`). O pacote `laravel-lang/lang` já gerencia os arquivos JSON para as traduções padrão do framework.
    *   **Chaves JSON:** Use o texto no idioma padrão (configurado em `APP_LOCALE`, geralmente inglês) como a chave de tradução.
        ```json
        // lang/en.json
        {
            "User Profile": "User Profile",
            "Invalid credentials.": "Invalid credentials."
        }

        // lang/pt_BR.json
        {
            "User Profile": "Perfil do Usuário",
            "Invalid credentials." : "Credenciais inválidas."
        }
        ```
    *   **Arquivos PHP (Exceção):** Arquivos PHP (`lang/xx/file.php`) podem ser usados para organizar traduções de pacotes vendor ou para casos muito específicos onde a estrutura de array aninhado é preferível, mas **NÃO É RECOMENDÁVEL** para traduções gerais da aplicação. Se usar, use chaves descritivas (ex: `auth.failed`).
*   **Parâmetros:** Use placeholders (`:name`) em strings de tradução e passe os valores como segundo argumento para `__()`.
    ```php
    // lang/pt_BR.json
    {
        "Welcome, :name!": "Bem-vindo, :name!"
    }

    // Código
    __('Welcome, :name!', ['name' => $userName]);
    ```
*   **Pluralização:** Use a função `trans_choice()` ou a diretiva `@choice` para lidar com pluralização de forma correta nos diferentes idiomas. Defina as formas singular e plural usando `|`.
    ```php
    // lang/pt_BR.json
    {
        "There is one apple|There are :count apples": "Existe uma maçã|Existem :count maçãs"
    }

    // Código
    trans_choice('There is one apple|There are :count apples', $count, ['count' => $count]);
    ```
*   **Consistência:** Mantenha as chaves de tradução e os textos consistentes entre os diferentes idiomas. Revise as traduções para garantir precisão e adequação cultural.

### Práticas de Código

*   **Nomes Expressivos:** Use nomes claros e auto-descritivos. O código deve ser legível quase como prosa. Evite abreviações obscuras ou nomes genéricos (`$data`, `$temp`, `$flag`).
*   **Evitar Comentários Desnecessários:** Código bem escrito e nomeado geralmente não precisa de comentários explicando *o quê* ele faz. Use comentários para explicar o *porquê* de uma decisão complexa, uma solução alternativa (workaround) ou para gerar documentação (DocBlocks). Mantenha os comentários atualizados.
*   **`.env` vs `config()`:** Variáveis de ambiente (`.env`) **DEVEM** ser acessadas **APENAS** dentro dos arquivos de configuração (`config/*.php`). No restante da aplicação (Controllers, Views, Services), **SEMPRE** use o helper `config('nome_arquivo.chave')`. Isso permite o cache de configuração (`php artisan config:cache`) crucial para performance em produção.
*   **Evitar Lógica em Views Blade:** Views (`.blade.php`) **DEVEM** conter o mínimo de lógica possível, focando na apresentação.
    *   **Permitido:** Estruturas `@if`, `@foreach`, `@forelse`, exibição de variáveis, chamadas a helpers simples (ex: `route()`, `asset()`, `__('key')`, ` Vite::asset()`), renderização de componentes.
    *   **NÃO PERMITIDO:** Consultas Eloquent (`User::all()`), lógica de negócio complexa, formatação de dados extensa (use Accessors/Mutators ou View Presenters/Transformers). Passe os dados já prontos do Controller/Componente para a View.
*   **Consultas Eloquent Eficientes:**
    *   **Evite N+1:** Use Eager Loading (`->with('relation')`) sempre que acessar relações dentro de um loop. Ferramentas como Laravel Debugbar ou Telescope ajudam a detectar N+1.
    *   **`select()`:** Selecione apenas as colunas que você realmente precisa, especialmente em tabelas largas ou consultas complexas (`->select('id', 'name', 'email')`).
    *   **Processamento em Lotes:** Para grandes volumes de dados, use `->chunk()`, `->chunkById()`, `->lazy()`, `->lazyById()` ou `->cursor()` em vez de `->get()` ou `->all()` para evitar esgotamento de memória.
*   **Atribuição em Massa (Mass Assignment):** Use `create()` e `update()` com arrays, mas **SEMPRE** defina a propriedade `$fillable` (preferencial) ou `$guarded` nos seus Models Eloquent para proteger contra vulnerabilidades de atribuição em massa.
*   **DocBlocks:** **DEVEM** ser usados para documentar todos os métodos públicos em Services, Actions, Models (métodos customizados), e Controllers (métodos não-CRUD ou complexos), e quaisquer métodos com lógica não trivial. Inclua `@param`, `@return`, `@throws`.
    ```php
    /**
     * Busca e processa pedidos de usuários ativos.
     *
     * @param \App\Models\User $user O usuário para buscar pedidos.
     * @param bool $includeDrafts Incluir pedidos em rascunho?
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Order>
     * @throws \App\Exceptions\UserNotFoundException Se o usuário não for encontrado.
     */
    public function getUserOrders(User $user, bool $includeDrafts = false): Collection
    {
        // ... implementation ...
    }
    ```
*   **Sintaxe Concisa:** Use helpers e sintaxe curta do Laravel quando apropriado (ex: `request()`, `auth()`, `view()`, `redirect()`, `optional()`).
*   **Early Return:** Retorne cedo de funções/métodos em caso de erro ou condição de saída, reduzindo o aninhamento de `if/else`.

```php
// Ruim: Aninhado
public function processOrder(Request $request)
{
    if ($request->has('product_id')) {
        $product = Product::find($request->product_id);
        if ($product) {
            if ($product->isInStock()) {
                // ... processar ...
                return response()->json(['success' => true]);
            } else {
                return response()->json(['error' => __('Fora de estoque')], 400); // Traduzido
            }
        } else {
            return response()->json(['error' => __('Produto não encontrado')], 404); // Traduzido
        }
    } else {
        return response()->json(['error' => __('ID do produto faltando')], 400); // Traduzido
    }
}

// Bom: Early Return
public function processOrder(Request $request)
{
    if (! $request->has('product_id')) {
        return response()->json(['error' => __('ID do produto faltando')], 400); // Traduzido
    }

    $product = Product::find($request->product_id);
    if (! $product) {
        return response()->json(['error' => __('Produto não encontrado')], 404); // Traduzido
    }

    if (! $product->isInStock()) {
        return response()->json(['error' => __('Fora de estoque')], 400); // Traduzido
    }

    // ... processar ...
    return response()->json(['success' => true]);
}
```

### Ferramentas de Desenvolvimento e Automação

*   **Script de Criação de Issues (`scripts/create_issue.py`):** Ferramenta Python para automação de criação/edição de Issues no GitHub a partir de arquivos de plano (`planos/*.txt`) e templates (`templates/issue_bodies/*.md`).
*   **Script de Geração de Contexto (`scripts/generate_context.py`):** Ferramenta Python para coletar informações abrangentes do projeto (código, Git, GitHub, ambiente, etc.) e salvá-las em `context_llm/code/<timestamp>/` para uso por LLMs, **com a capacidade de executar seletivamente estágios de coleta via argumento `--stages` e copiar arquivos de estágios não executados do contexto anterior para garantir a completude do diretório gerado.**
    **Exemplos de Uso:**
    *   **Execução Completa (Padrão):** `python scripts/generate_context.py`
        *   Coleta todas as informações (Git, Artisan, GitHub, etc.) e cria um novo diretório de contexto completo.
    *   **Execução Seletiva (Ex: apenas Git e Artisan):** `python scripts/generate_context.py --stages git artisan`
        *   Executa apenas os estágios `git` e `artisan`, gerando novos arquivos para eles. Arquivos de outros estágios (ex: `github_issues_details.json`) serão copiados do diretório de contexto gerado anteriormente, se existirem.
    *   **Atualização de Detalhes de Issue (Ex: apenas GitHub Issues):** `python scripts/generate_context.py --stages github_issues`
        *   Atualiza apenas os detalhes das Issues do GitHub, copiando o restante do contexto da última execução para garantir um diretório de contexto completo.
*   **Scripts de Interação com LLM (`scripts/llm_interact.py` e `scripts/tasks/llm_task_*.py`):**
    A ferramenta de interação com LLM foi modularizada. O script principal `scripts/llm_interact.py` agora funciona como um **dispatcher**. Você pode invocar tarefas específicas através dele ou executar os scripts de tarefa individuais diretamente.
    *   **Dispatcher:** `python scripts/llm_interact.py <nome_da_tarefa> [argumentos_da_tarefa...]`
        Ex: `python scripts/llm_interact.py resolve-ac --issue 123 --ac 1`
        Se `<nome_da_tarefa>` for omitido, o dispatcher listará as tarefas disponíveis interativamente.
    *   **Scripts de Tarefa Individuais:** Localizados em `scripts/tasks/`, podem ser executados diretamente.
        Ex: `python scripts/tasks/llm_task_resolve_ac.py --issue 123 --ac 1 [outros_argumentos_comuns...]`
    *   **Funcionalidades Comuns:** As funcionalidades centrais (configuração, parsing de argumentos comuns, carregamento de contexto, interação com API, I/O) estão em `scripts/llm_core/`. Incluem: pré-injeção de arquivos essenciais no contexto da LLM seletora; gerenciamento proativo de limites de tokens e RPM da API Gemini (cálculo dinâmico de `MAX_INPUT_TOKENS_PER_CALL`, redução de contexto por sumário/truncamento e rate limiter de chamadas); e melhorias na experiência do usuário ao selecionar contexto interativamente.
    *   **Argumentos Comuns:** Use `-h` ou `--help` em qualquer script de tarefa ou no dispatcher para ver as opções comuns e específicas da tarefa. Destacam-se: `--issue`, `--ac`, `--observation`, `--two-stage` (fluxo com meta-prompt), `--select-context` (para seleção interativa de contexto, agora com exibição de contagem de tokens e tratamento de arquivos ausentes/truncados), `--web-search` (com tool calling), `--generate-context` (para acionar o script de geração de contexto), etc.
    * Requer `google-genai`, `python-dotenv`, `tqdm` e uma `GEMINI_API_KEY` válida no arquivo `.env`.

## Uso de Termos RFC 2119 na Documentação

Ao escrever documentação, use os termos da [RFC 2119](https://datatracker.ietf.org/doc/html/rfc2119) para indicar níveis de obrigatoriedade:

| Inglês (RFC 2119)           | Português (Adotado)         | Significado                                     |
| :-------------------------- | :-------------------------- | :---------------------------------------------- |
| MUST, REQUIRED, SHALL       | **DEVE, DEVEM, REQUER**     | Obrigação absoluta.                             |
| MUST NOT, SHALL NOT         | **NÃO DEVE, NÃO DEVEM**     | Proibição absoluta.                           |
| SHOULD, RECOMMENDED         | **PODERIA, PODERIAM, RECOMENDÁVEL** | Forte recomendação, exceções justificadas. |
| SHOULD NOT, NOT RECOMMENDED | **NÃO PODERIA, NÃO RECOMENDÁVEL** | Forte desaconselhamento, exceções justificadas. |
| MAY, OPTIONAL               | **PODE, PODEM, OPCIONAL**   | Verdadeiramente opcional, sem preferência.      |

Exemplo: _"O Model **DEVE** ter a propriedade `$fillable` definida."_ vs. _"Você **PODERIA** usar um Service para encapsular a lógica de email."_ vs. _"Todo texto visível ao usuário **DEVE** usar a função `__()`."_