<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso indisponível · Sessia</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 text-stone-800">
    <main class="mx-auto max-w-lg px-6 py-20">
        <p class="eyebrow">Sessia</p>
        <h1 class="page-title">Acesso indisponível</h1>
        <p class="mt-5 text-stone-600">Você não tem permissão para acessar este negócio ou realizar esta ação.</p>
        @auth
            <form action="{{ route('logout') }}" method="POST" class="mt-8">
                @csrf
                <button class="primary-button">Sair e acessar outra conta</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="primary-button mt-8">Entrar na minha conta</a>
        @endauth
    </main>
</body>
</html>
