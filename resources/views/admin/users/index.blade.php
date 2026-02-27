@extends('layouts.app')
@section('title', 'Gestion des utilisateurs')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Utilisateurs</h1>
        <a href="{{ route('admin.users.create') }}"
           class="px-4 py-2 rounded-lg text-white text-sm font-medium"
           style="background-color: #1E3A5F;">
            + Nouvel utilisateur
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead style="background-color: #1E3A5F;">
                <tr>
                    <th class="px-4 py-3 text-left text-white font-medium">Nom</th>
                    <th class="px-4 py-3 text-left text-white font-medium">Email</th>
                    <th class="px-4 py-3 text-left text-white font-medium">Rôle</th>
                    <th class="px-4 py-3 text-left text-white font-medium">Service</th>
                    <th class="px-4 py-3 text-left text-white font-medium">Statut</th>
                    <th class="px-4 py-3 text-left text-white font-medium">Auth</th>
                    <th class="px-4 py-3 text-left text-white font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-700">
                            {{ $user->role }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $user->department ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                            {{ $user->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $user->status }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 text-xs">
                        {{ $user->ldap_dn ? 'LDAP' : 'Local' }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.users.edit', $user) }}"
                               class="text-blue-600 hover:underline text-xs">Modifier</a>
                            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
                                @csrf
                                <button class="text-orange-600 hover:underline text-xs">Réinit. MDP</button>
                            </form>
                            @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                  onsubmit="return confirm('Désactiver cet utilisateur ?')">
                                @csrf @method('DELETE')
                                <button class="text-red-600 hover:underline text-xs">Désactiver</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                        Aucun utilisateur trouvé.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
</div>
@endsection
