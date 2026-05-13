<div style="position:relative;">
    <a href="{{ route('datagrid.show', $table) }}"
       style="display:block;background:var(--pd-bg);border:1px solid var(--pd-border);
              border-radius:12px;padding:20px;text-decoration:none;
              transition:border-color .15s,box-shadow .15s;"
       onmouseover="this.style.borderColor='var(--pd-primary)';this.style.boxShadow='0 2px 8px rgba(0,0,0,.08)'"
       onmouseout="this.style.borderColor='var(--pd-border)';this.style.boxShadow='none'">
        <div style="font-size:15px;font-weight:600;color:var(--pd-text);margin-bottom:4px;">
            {{ $table->label }}
        </div>
        @if($table->description)
        <div style="font-size:12px;color:var(--pd-muted);margin-bottom:12px;">{{ $table->description }}</div>
        @endif
        <div style="font-size:11px;color:var(--pd-muted);">
            {{ $table->columns_count }} colonne{{ $table->columns_count !== 1 ? 's' : '' }}
        </div>
    </a>

    {{-- Menu déplacer vers dossier (admin) --}}
    @if(auth()->user()->isAdmin() && isset($allFolders) && $allFolders->isNotEmpty())
    <div style="position:absolute;top:10px;right:10px;">
        <div style="position:relative;display:inline-block;">
            <button onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='block'?'none':'block'"
                    style="padding:3px 7px;background:none;border:1px solid var(--pd-border);border-radius:6px;font-size:11px;color:var(--pd-muted);cursor:pointer;background:var(--pd-bg);">
                📁
            </button>
            <div style="display:none;position:absolute;right:0;top:100%;margin-top:4px;background:var(--pd-bg);border:1px solid var(--pd-border);border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:10;min-width:180px;padding:4px 0;">
                @if($folder)
                <form method="POST" action="{{ route('datagrid.table.move', $table) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="folder_id" value="">
                    <button type="submit" style="width:100%;padding:8px 14px;background:none;border:none;text-align:left;font-size:12px;color:var(--pd-text);cursor:pointer;">
                        ↖ Retirer du dossier
                    </button>
                </form>
                @endif
                @foreach($allFolders as $f)
                @if(!$folder || $f->id !== $folder->id)
                <form method="POST" action="{{ route('datagrid.table.move', $table) }}">
                    @csrf @method('PATCH')
                    <input type="hidden" name="folder_id" value="{{ $f->id }}">
                    <button type="submit" style="width:100%;padding:8px 14px;background:none;border:none;text-align:left;font-size:12px;color:var(--pd-text);cursor:pointer;">
                        📁 {{ $f->label }}
                    </button>
                </form>
                @endif
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
