<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin — Vérification TOTP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-md">
        <div class="text-center mb-6">
            <div style="font-size:2.5rem;margin-bottom:0.5rem">🔐</div>
            <h1 class="text-2xl font-bold" style="color: #1E3A5F;">Vérification en deux étapes</h1>
            <p class="text-gray-500 text-sm mt-1">Saisissez le code de votre application d'authentification</p>
        </div>

        @if($errors->any())
            <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('super-admin.login.totp.verify') }}">
            @csrf
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">Code à 6 chiffres</label>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" autocomplete="one-time-code" autofocus
                       placeholder="123456"
                       class="w-full border border-gray-300 rounded-lg px-3 py-3 text-center text-xl tracking-widest font-mono focus:outline-none focus:ring-2"
                       style="letter-spacing:0.4em">
            </div>
            <button type="submit"
                    class="w-full py-2 rounded-lg text-white font-medium text-sm"
                    style="background-color: #1E3A5F;">
                Vérifier
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="{{ route('super-admin.login') }}" class="text-xs text-gray-400 hover:text-gray-600">
                ← Retour à la connexion
            </a>
        </div>
    </div>
</body>
</html>
