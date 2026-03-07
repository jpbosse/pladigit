@extends('layouts.super-admin')
@section('title', 'Super Administration — Organisations')
 
@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Organisations clientes</h1>
        <a href="{{ route('super-admin.organizations.create') }}"
           class="px-4 py-2 rounded-lg text-white text-sm font-medium"
           style="background-color: #1E3A5F;">
            + Nouvelle organisation
        </a>
    </div>
 
    {{-- Messages flash --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm">
            {{ session('success') }}
        </div>
    @endif
 
    {{-- Tableau des organisations --}}
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead style="background-color: #1E3A5F;">
                <tr>
                    @foreach(['Organisation', 'Slug', 'Plan', 'Utilisateurs', 'Statut', 'Actions'] as $col)
                    <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase">
                        {{ $col }}
                    </th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($orgs as $org)
                <tr class="hover:bg-gray-50">
		<td class="px-4 py-3 font-medium text-gray-800">
		    <a href="{{ route('super-admin.organizations.show', $org) }}" 
		       class="hover:underline" style="color: #1E3A5F;">
		        {{ $org->name }}
		    </a>
		</td>




                    <td class="px-4 py-3 font-mono text-sm text-gray-500">{{ $org->slug }}</td>
                    <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                            {{ $org->plan === 'enterprise' ? 'bg-purple-100 text-purple-700' :
                               ($org->plan === 'assistance' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600') }}">
                            {{ ['communautaire'=>'Communautaire','assistance'=>'Assistance','enterprise'=>'Enterprise'][$org->plan] ?? ucfirst($org->plan) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">— / {{ $org->max_users }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 rounded-full text-xs font-medium
                            {{ $org->status === 'active' ? 'bg-green-100 text-green-700' :
                               ($org->status === 'suspended' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700') }}">
                            {{ ['active'=>'Actif','suspended'=>'Suspendu','pending'=>'En attente','archived'=>'Archivé'][$org->status] ?? ucfirst($org->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm space-x-2">
                        @if($org->status === 'active')
                        <form method="POST" action="{{ route('super-admin.organizations.suspend', $org) }}"
                              class="inline" onsubmit="return confirm('Suspendre {{ $org->name }} ?')">
                            @csrf
                            <button class="text-red-600 hover:underline text-xs">Suspendre</button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('super-admin.organizations.activate', $org) }}"
                              class="inline">
                            @csrf
                            <button class="text-green-600 hover:underline text-xs">Activer</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                        Aucune organisation. Créez la première ci-dessus.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
 
    {{-- Pagination --}}
    <div class="mt-4">{{ $orgs->links() }}</div>
</div>
@endsection
