@extends('layouts.admin')
@section('title', 'Personnalisation')

@section('admin-content')
<div class="max-w-2xl mx-auto px-4 py-6">

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Personnalisation</h1>

    @if(session('success'))
        <div class="bg-green-50 border border-green-300 text-green-700 rounded-lg p-3 mb-4 text-sm flex items-center gap-2">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-300 text-red-700 rounded-lg p-3 mb-4 text-sm">
            @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.branding.update') }}" enctype="multipart/form-data">
        @csrf

        {{-- Couleur principale --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-1">Couleur principale</h2>
            <p class="text-xs text-gray-400 mb-4">Utilisée dans l'en-tête, les boutons et les éléments actifs.</p>
            <div class="flex items-center gap-3">
                <input type="color" name="primary_color" id="color_picker"
                       value="{{ $org->primary_color ?? '#1E3A5F' }}"
                       class="w-12 h-10 rounded-lg cursor-pointer border border-gray-200 p-0.5">
                <input type="text" id="color_hex"
                       value="{{ $org->primary_color ?? '#1E3A5F' }}"
                       maxlength="7"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-28 font-mono"
                       placeholder="#1E3A5F">
                <div id="color_preview" class="w-8 h-8 rounded-lg border border-gray-200"
                     style="background: {{ $org->primary_color ?? '#1E3A5F' }};"></div>
            </div>
        </div>

        {{-- Logo --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-1">Logo</h2>
            <p class="text-xs text-gray-400 mb-4">Affiché dans la topbar et la page de connexion.</p>

            @if(filled($org->logo_path))
                <div class="mb-4">
                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200 inline-flex items-center gap-3">
                        <img src="{{ asset('storage/' . $org->logo_path) }}"
                             alt="Logo actuel" class="h-12 object-contain"
                             onerror="this.closest('div').style.display='none'">
                        <div class="text-xs text-gray-500">Logo actuel</div>
                    </div>
                    <div class="mt-2">
                        <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 text-red-600 text-xs font-medium cursor-pointer hover:bg-red-100 transition-colors">
                            <input type="checkbox" name="remove_logo" value="1" class="w-3.5 h-3.5 accent-red-500">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                            Supprimer le logo
                        </label>
                    </div>
                </div>
            @endif

            <label id="logo-drop" class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 rounded-xl p-6 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors">
                <svg width="28" height="28" fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <div class="text-sm text-gray-500">Glissez un fichier ici ou <span class="text-blue-600 font-medium">parcourir</span></div>
                <div class="text-xs text-gray-400">PNG, JPG ou SVG — max 2 Mo</div>
                <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg" class="hidden" id="logo-input">
            </label>

            <div id="logo-preview-wrap" class="hidden mt-3 p-3 bg-gray-50 rounded-lg inline-flex items-center gap-3 border border-blue-200">
                <img id="logo-preview-img" src="" alt="Aperçu" class="h-12 object-contain">
                <div>
                    <div class="text-xs font-medium text-gray-700" id="logo-preview-name"></div>
                    <div class="text-xs text-gray-400" id="logo-preview-size"></div>
                </div>
            </div>
        </div>

        {{-- Image de fond login --}}
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-1">Image de fond (page de connexion)</h2>
            <p class="text-xs text-gray-400 mb-4">Affichée en arrière-plan sur l'écran de login.</p>

            @if(filled($org->login_bg_path))
                <div class="mb-4">
                    <div class="rounded-lg overflow-hidden border border-gray-200 inline-block w-full">
                        <img src="{{ asset('storage/' . $org->login_bg_path) }}"
                             alt="Fond actuel" class="h-36 w-full object-cover"
                             onerror="this.closest('div').style.display='none'">
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-xs text-gray-500 flex items-center gap-1">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Image de fond active
                        </span>
                        <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-red-200 bg-red-50 text-red-600 text-xs font-medium cursor-pointer hover:bg-red-100 transition-colors">
                            <input type="checkbox" name="remove_login_bg" value="1" class="w-3.5 h-3.5 accent-red-500">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                            Supprimer l'image de fond
                        </label>
                    </div>
                </div>
            @endif

            <label id="bg-drop" class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-300 rounded-xl p-6 cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-colors">
                <svg width="28" height="28" fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                <div class="text-sm text-gray-500">Glissez une image ici ou <span class="text-blue-600 font-medium">parcourir</span></div>
                <div class="text-xs text-gray-400">PNG ou JPG — max 4 Mo</div>
                <input type="file" name="login_bg" accept=".png,.jpg,.jpeg" class="hidden" id="bg-input">
            </label>

            <div id="bg-preview-wrap" class="hidden mt-3 rounded-lg overflow-hidden border border-blue-200">
                <img id="bg-preview-img" src="" alt="Aperçu fond" class="h-28 w-full object-cover">
                <div class="px-3 py-1.5 bg-gray-50 border-t border-blue-100 flex gap-3">
                    <span class="text-xs font-medium text-gray-700" id="bg-preview-name"></span>
                    <span class="text-xs text-gray-400" id="bg-preview-size"></span>
                </div>
            </div>
        </div>

        <button type="submit"
                class="px-6 py-2.5 rounded-lg text-white text-sm font-semibold shadow hover:opacity-90 transition-opacity"
                style="background-color: var(--pd-navy);">
            Sauvegarder les modifications
        </button>
    </form>
</div>

<script>
// Couleur
const picker   = document.getElementById('color_picker');
const hexInput = document.getElementById('color_hex');
const preview  = document.getElementById('color_preview');
picker.addEventListener('input', () => { hexInput.value = picker.value; preview.style.background = picker.value; });
hexInput.addEventListener('input', () => {
    if (/^#[0-9A-Fa-f]{6}$/.test(hexInput.value)) { picker.value = hexInput.value; preview.style.background = hexInput.value; }
});

// Aperçu fichier
function setupFilePreview(inputId, wrapId, imgId, nameId, sizeId, dropId) {
    const input = document.getElementById(inputId);
    const wrap  = document.getElementById(wrapId);
    const img   = document.getElementById(imgId);
    const name  = document.getElementById(nameId);
    const size  = document.getElementById(sizeId);
    const drop  = document.getElementById(dropId);

    function handleFile(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            img.src = e.target.result;
            name.textContent = file.name;
            size.textContent = (file.size / 1024 / 1024).toFixed(2) + ' Mo';
            wrap.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }
    input.addEventListener('change', () => handleFile(input.files[0]));
    drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('border-blue-500'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('border-blue-500'));
    drop.addEventListener('drop', e => {
        e.preventDefault(); drop.classList.remove('border-blue-500');
        if (e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; handleFile(e.dataTransfer.files[0]); }
    });
}
setupFilePreview('logo-input', 'logo-preview-wrap', 'logo-preview-img', 'logo-preview-name', 'logo-preview-size', 'logo-drop');
setupFilePreview('bg-input',   'bg-preview-wrap',   'bg-preview-img',   'bg-preview-name',   'bg-preview-size',   'bg-drop');
</script>
@endsection
