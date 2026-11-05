# Sessia

SaaS para gestão de atendimentos de estética em domicílio. Laravel 13, Livewire 4,
Blade, Tailwind 4 e MySQL/InnoDB, com dados isolados por negócio.

O MVP integra autenticação, clientes, endereços, catálogo e preços personalizados,
agenda, atendimentos avulsos, pacotes mistos, planos mensais, competências, sessões,
cobranças, parcelamento, pagamentos manuais, estornos e dashboard real.
Pix utiliza a chave de recebimento do próprio negócio, sem conciliação bancária.

## Executar

1. `composer install`
2. Configure o ambiente local a partir de `.env.example`, com MySQL e locale pt_BR.
3. Gere APP_KEY somente se ainda estiver vazia.
4. `php artisan migrate`
5. `npm ci --ignore-scripts` e `npm run build`
6. `php artisan serve`

Abra `/cadastro`. Não há credenciais padrão.
Para geração diária dos planos, configure o scheduler do Laravel.
Execução manual: `php artisan sessia:generate-plans --months=2`.

Testes: `php artisan test`. Estilo: `php vendor/bin/pint --test`.

Consulte [status, decisões, testes e limites](docs/implementation-status.md)
e [as regras do projeto](AGENTS.md). A revisão visual em navegador e os recursos
condicionados de QR/Pix EMV ainda não estão concluídos.