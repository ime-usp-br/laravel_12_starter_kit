<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bem-vindo(a)') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Card de Apresentação --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 lg:p-8 text-gray-900 dark:text-gray-100">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        Bem-vindo(a) ao {{ config('app.name', 'Laravel') }} Starter Kit!
                    </h1>

                    <p class="mt-4 text-gray-700 dark:text-gray-400 leading-relaxed">
                        Esta é uma página de exemplo provisória para visualizar o cabeçalho padrão da USP integrado
                        acima. O restante do conteúdo pode ser personalizado conforme a necessidade da sua aplicação.
                    </p>

                    <p class="mt-4 text-gray-700 dark:text-gray-400 leading-relaxed">
                        A partir daqui, você pode:
                    </p>
                    <ul class="mt-2 list-disc list-inside text-gray-700 dark:text-gray-400">
                        {{-- Links para as rotas de autenticação --}}
                        @guest
                            <li><a href="{{ route('login.local') }}" class="underline hover:text-gray-900 dark:hover:text-white">Fazer login local</a></li>
                            <li><a href="{{ route('login') }}" class="underline hover:text-gray-900 dark:hover:text-white">Fazer login com Senha Única USP</a></li>
                            <li><a href="{{ route('register') }}" class="underline hover:text-gray-900 dark:hover:text-white">Registrar-se</a></li>
                        @endguest
                        @auth
                            <li><a href="{{ route('dashboard') }}" class="underline hover:text-gray-900 dark:hover:text-white">Acessar seu Dashboard</a></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}" class="inline">
                                    @csrf
                                    <a href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); this.closest('form').submit();"
                                       class="underline hover:text-gray-900 dark:hover:text-white">
                                        Fazer Logout
                                    </a>
                                </form>
                            </li>
                        @endauth
                    </ul>

                    <div class="mt-6">
                        <p class="text-xs text-gray-400 dark:text-gray-500">
                            O cabeçalho acima foi gerado pelo componente <code><x-usp.header /></code>.
                            Verifique os caminhos das imagens e as cores no componente e no <code>tailwind.config.js</code> se necessário.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>