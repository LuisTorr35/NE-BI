<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin · SOLE</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center">
    <div class="w-full max-w-sm bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-xl font-bold mb-1">SOLE Admin</h1>
        <p class="text-sm text-slate-500 mb-6">Panel de administración y BI.</p>

        <form action="{{ route('admin.login.attempt') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Correo</label>
                <input type="email" name="email" value="{{ old('email', 'admin@nebi.com') }}" required autofocus
                       class="w-full rounded-lg border border-slate-300 px-3 py-2">
                @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Contraseña</label>
                <input type="password" name="password" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2">
            </div>
            <button class="w-full bg-slate-900 hover:bg-slate-800 text-white rounded-lg py-2.5 font-medium">
                Ingresar
            </button>
        </form>

        <div class="mt-6 text-xs text-slate-500 bg-slate-50 rounded-lg p-3">
            <strong>Demo:</strong> <code>admin@nebi.com</code> / <code>admin123</code>
        </div>
    </div>
</body>
</html>
