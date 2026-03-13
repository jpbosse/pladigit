@props([
    'size'  => 32,
    'color' => '#fff',
    'class' => '',
])
{{--
    Logo Pladigit — SVG 100% paths, zéro dépendance police.
    Lettre B stylisée, trait calligraphique inspiré du logo original.
--}}
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 40 40"
     fill="none" xmlns="http://www.w3.org/2000/svg"
     class="{{ $class }}" style="flex-shrink:0;display:block;" aria-label="Pladigit">

    {{-- Trait vertical gauche du B --}}
    <path d="M10 6 C8 6 7 7 7 8.5 L7 31.5 C7 33 8 34 10 34"
          stroke="{{ $color }}" stroke-width="2.2" stroke-linecap="round" fill="none"/>

    {{-- Bosse haute du B --}}
    <path d="M10 6 L17 6 C22 6 25 8.5 25 13 C25 17.5 22 20 17 20 L10 20"
          stroke="{{ $color }}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>

    {{-- Bosse basse du B (plus large) --}}
    <path d="M10 20 L18 20 C24 20 28 23 28 28 C28 33 24 34 18 34 L10 34"
          stroke="{{ $color }}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>

    {{-- Empattement bas --}}
    <line x1="5" y1="34" x2="13" y2="34"
          stroke="{{ $color }}" stroke-width="2" stroke-linecap="round" opacity="0.7"/>

</svg>
