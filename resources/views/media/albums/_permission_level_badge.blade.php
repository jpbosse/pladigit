@php
    $colors = match($level->value) {
        'none'     => 'bg-red-100 text-red-700',
        'view'     => 'bg-blue-100 text-blue-700',
        'download' => 'bg-amber-100 text-amber-700',
        'upload'   => 'bg-purple-100 text-purple-700',
        'admin'    => 'bg-green-100 text-green-700',
        default    => 'bg-gray-100 text-gray-600',
    };
@endphp
<span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $colors }}">
    {{ $level->label() }}
</span>
