<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\User;
use App\Services\Ged\WopiTokenService;
use App\Services\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Ouvre un document GED dans l'éditeur Collabora Online.
 *
 * La page soumet un form POST vers Collabora (spec WOPI) qui s'affiche dans l'iframe.
 * Collabora appelle en retour les endpoints WOPI pour récupérer le fichier.
 */
class GedEditorController extends Controller
{
    public function __construct(
        private readonly WopiTokenService $tokens,
    ) {}

    /**
     * GET /ged/documents/{document}/editor
     */
    public function show(GedDocument $document): View|RedirectResponse
    {
        $collaboraUrl = rtrim((string) config('collabora.url', ''), '/');

        if ($collaboraUrl === '') {
            return redirect()
                ->route('ged.folders.show', $document->folder_id)
                ->with('error', 'Collabora Online n\'est pas configuré sur cette instance.');
        }

        $supportedMimes = (array) config('collabora.supported_mimes', []);

        if (! in_array($document->mime_type, $supportedMimes, true)) {
            return redirect()
                ->route('ged.folders.show', $document->folder_id)
                ->with('error', 'Ce type de fichier n\'est pas supporté par Collabora Online.');
        }

        /** @var User $user */
        $user = auth()->user();
        $wopiToken = $this->tokens->generate($document, $user);

        // URL fixe pour WOPISrc : ne dépend plus du sous-domaine tenant.
        // Collabora résout le tenant depuis le préfixe du token ({slug}:{raw}).
        $wopiBase = rtrim((string) config('collabora.wopi_url', config('app.url', '')), '/');
        $wopiSrc = $wopiBase.route('wopi.files.info', $document->id, false);

        $orgSlug = app(TenantManager::class)->currentOrFail()->slug;
        $accessToken = $this->tokens->buildAccessToken($wopiToken, $orgSlug);

        $editorPath = ltrim((string) config('collabora.editor_path', '/browser/dist/cool.html'), '/');

        // WOPI spec : l'action URL contient WOPISrc, le token est envoyé via form POST
        $actionUrl = $collaboraUrl.'/'.$editorPath.'?WOPISrc='.urlencode($wopiSrc);

        return view('ged.editor', [
            'document' => $document,
            'actionUrl' => $actionUrl,
            'accessToken' => $accessToken,
            'ttlMs' => $this->tokens->ttlMs(),
        ]);
    }
}
