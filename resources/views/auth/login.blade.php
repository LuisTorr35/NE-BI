@extends('layouts.app')
@section('title', 'Iniciar sesión · SOLE')

@section('content')
<div class="max-w-md mx-auto bg-white rounded-xl shadow-sm border border-slate-200 p-8 mt-6">
    <h1 class="text-xl font-bold mb-1">Iniciar sesión</h1>
    <p class="text-sm text-slate-500 mb-6">Ingresa con tu cuenta de cliente.</p>

    <form action="{{ route('login.attempt') }}" method="POST" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium mb-1">Correo</label>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-lg border border-slate-300 px-3 py-2">
            @error('email') <p class="text-red-500 text-sm mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Contraseña</label>
            <input type="password" name="password" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2">
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember"> Recordarme
        </label>

        <button class="w-full bg-sky-600 hover:bg-sky-700 text-white rounded-lg py-2.5 font-medium">
            Ingresar
        </button>
    </form>

    <div class="mt-6 text-xs text-slate-500 bg-slate-50 rounded-lg p-3">
        <strong>Demo:</strong> usa cualquier correo de cliente sembrado (ej.
        <code>isabel.nieves.50001@correo.com</code>) con la contraseña <code>password</code>.
    </div>
</div>
@endsection
