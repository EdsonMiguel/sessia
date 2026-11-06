# Sessia

SaaS para gestão de atendimentos de estética em domicílio. Laravel 13, Livewire 4,
Blade, Tailwind 4 e MySQL/InnoDB, com dados isolados por negócio.

O MVP integra autenticação, clientes, endereços, catálogo e preços personalizados,
agenda, atendimentos avulsos, pacotes mistos, planos mensais, competências, sessões,
cobranças, parcelamento, pagamentos manuais, estornos e dashboard real.
Pix Copia e Cola usa `pix-utils` e a chave do próprio negócio, com saldo pendente
por parcela, descrição e compartilhamento por `wa.me`. A baixa continua manual.

## Executar

### Limpar os dados do negócio

Em **Minha conta → Limpar dados cadastrados**, a proprietária pode
apagar permanentemente os dados operacionais do negócio selecionado, incluindo
históricos financeiros. A ação exige senha atual e a expressão `APAGAR TUDO`.
Conta, vínculos e configurações permanecem; outros negócios não são afetados.
A limpeza é transacional, sem desativar chaves estrangeiras, e mantém um registro
de auditoria com responsável, data e contagens, sem conteúdo dos registros apagados.
Não há recuperação pela aplicação. A instalação da funcionalidade não executa a limpeza.

### Coordenadas dos endereços

Defina `GOOGLE_MAPS_API_KEY` no `.env` com uma chave de servidor habilitada para a
[Geocoding API do Google](https://developers.google.com/maps/documentation/geocoding/guides-v3/start).
Depois execute `php artisan config:clear` e `php artisan migrate`.
A chave é lida em `config/services.php` e não é enviada ao navegador.

Ao salvar um endereço no cadastro da cliente ou no formulário de endereços, a
aplicação consulta o Google e grava `latitude`, `longitude` e a data da consulta.
São enviados apenas logradouro, número, bairro, cidade, estado, CEP e país;
complementos, referências e dados pessoais da cliente não compõem a consulta.
Sem chave, sem resultado único ou em caso de falha, o cadastro é preservado com
coordenadas nulas e aviso. Salvar novamente tenta outra consulta. Endereços antigos
não são consultados em lote; edite e salve para obter suas coordenadas.

### Instalação

1. `composer install`
2. Configure o ambiente local a partir de `.env.example`, com MySQL e locale pt_BR.
3. Gere APP_KEY somente se ainda estiver vazia.
4. `php artisan migrate`
5. `npm ci --ignore-scripts` e `npm run build`
6. `php artisan serve`

Abra `/cadastro`. Não há credenciais padrão.
Para ativar o catálogo público, preencha **Slug do catálogo** e **Telefone / WhatsApp**
em `/configuracoes` e marque **Exibir no catálogo público** nos procedimentos e pacotes desejados.
A página `/catalogo/{slug}` não exige login e mostra somente procedimentos e pacotes ativos
publicados. Selecionar um serviço ou pacote e usar o botão de WhatsApp prepara uma mensagem
para o telefone do negócio; o visitante confirma o envio. Remover o slug desativa
o catálogo. Alterar o slug muda a URL, sem redirecionar links antigos.
Na atualização, execute `php artisan migrate` e `npm run build`.
Cadastre modelos em **Pacotes** (`/pacotes`), com procedimentos, quantidades e valor total.
Na nova contratação, selecione **Pacote** e use um modelo cadastrado ou **Personalizar novo pacote**.
Os itens e o valor podem ser negociados apenas para aquela contratação, sem alterar o modelo.
Editar ou desativar um modelo não modifica contratos anteriores. Pacotes publicados incluem
os nomes dos seus procedimentos, mesmo que estes não estejam publicados individualmente;
procedimentos inativos suspendem a exibição do pacote e impedem carregar o modelo.
Para geração diária dos planos, configure o scheduler do Laravel.
Execução manual: `php artisan sessia:generate-plans --months=2`.

Testes: `php artisan test` e `node --test tests/js/pix-payment.test.js`.
Estilo: `php vendor/bin/pint --test`.
Execute `npm ci --ignore-scripts` e `npm run build` após atualizar as dependências.
Livewire e Alpine são compilados pelo Vite; refaça o build após atualizar Livewire.

### Laragon no Windows

Inicie Apache e MySQL no Laragon. Use PHP 8.3 ou superior e Node.js 20.19+
(na linha 20) ou 22.12+ para as dependências atuais. Execute os comandos na
raiz do projeto, em um terminal com `php`, `composer` e `npm` disponíveis;
o terminal do Laragon inclui os executáveis selecionados nele.

O virtual host `sessia.test` deve apontar para `C:/laragon/www/sessia/public`.
Com o Apache servindo esse endereço, não é necessário executar `php artisan serve`.

No PowerShell, instale as dependências e crie o ambiente apenas se ainda não existir:

```powershell
composer install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Edite o `.env` local para usar `APP_URL=http://sessia.test` e as credenciais do
MySQL do Laragon. Se a instalação local usar `root` sem senha, configure:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sessia
DB_USERNAME=root
DB_PASSWORD=
```

Crie o banco `sessia` no MySQL pelo gerenciador de banco do Laragon, caso ainda
não exista. Em seguida, gere a chave somente se estiver vazia, aplique as
migrações e compile os arquivos da interface:

```powershell
php artisan config:clear
if (Select-String -Path .env -Pattern '^APP_KEY=\s*$' -Quiet) {
    php artisan key:generate
}
php artisan migrate
npm ci --ignore-scripts
npm run build
```

Abra `http://sessia.test/cadastro`. Em caso de erro 500, consulte
`storage/logs/laravel.log`: `MissingAppKeyException` indica ausência de `.env`
ou de `APP_KEY`; `Vite manifest not found` indica que falta executar o build.
Erros de conexão ou tabela inexistente exigem revisar as credenciais, o banco
e as migrações, pois sessões e cache usam o MySQL por padrão.

Consulte [status, decisões, testes e limites](docs/implementation-status.md)
e [as regras do projeto](AGENTS.md). A geração de imagem de QR Code ainda não está disponível.
