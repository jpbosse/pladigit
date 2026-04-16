<?php

namespace App\Http\Controllers\Ged;

use App\Http\Controllers\Controller;
use App\Models\Tenant\GedDocument;
use App\Models\Tenant\User;
use App\Services\Ged\WopiTokenService;
use App\Services\TenantManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

        // URL vide = Collabora proxyfié sous le même vhost que l'app.
        // L'hôte courant est valable pour tous les tenants sans modifier .env.
        if ($collaboraUrl === '') {
            $collaboraUrl = rtrim(request()->getSchemeAndHttpHost(), '/');
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

        // Le tenant est encodé dans le chemin du WOPISrc : /wopi/{tenant}/files/{id}.
        // Collabora ajoute toujours "?access_token=TOKEN" avec "?" même si le WOPISrc
        // contient déjà une query string (ex. "?tenant=slug"), ce qui crée une URL
        // invalide avec deux "?". En mettant le tenant dans le chemin, le WOPISrc
        // n'a aucun "?" et Collabora peut ajouter son access_token sans corruption.
        $wopiBase = rtrim((string) config('collabora.wopi_url', config('app.url', '')), '/');
        $orgSlug = app(TenantManager::class)->currentOrFail()->slug;
        $wopiSrc = $wopiBase.'/wopi/'.$orgSlug.'/files/'.$document->id;
        $accessToken = $wopiToken->token;

        // Récupère le chemin depuis /hosting/discovery pour avoir le hash de version
        // correct (ex: /browser/4610258811/cool.html). Sans ce hash, Collabora
        // détecte un mismatch de version et sert un JS périmé qui n'envoie pas
        // l'access_token au wsd → GetFile reçoit un token vide → échec.
        $editorPath = $this->resolveEditorPath();

        // WOPI spec : l'action URL contient WOPISrc, le token est envoyé via form POST
        // lang=fr force l'interface Collabora en français
        $actionUrl = $collaboraUrl.'/'.$editorPath.'WOPISrc='.urlencode($wopiSrc).'&lang=fr';

        return view('ged.editor', [
            'document' => $document,
            'actionUrl' => $actionUrl,
            'accessToken' => $accessToken,
            'ttlMs' => $this->tokens->ttlMs(),
        ]);
    }

    /**
     * Récupère le chemin de l'éditeur depuis /hosting/discovery de Collabora.
     *
     * Collabora intègre un hash de version dans le chemin (ex: /browser/4610258811/cool.html?).
     * Sans ce hash, le navigateur peut charger un JS périmé depuis son cache, ce qui
     * empêche l'access_token d'être transmis correctement au wsd → GetFile échoue.
     *
     * Le résultat est mis en cache 24h (change seulement lors d'une mise à jour Collabora).
     * En cas d'échec, on retombe sur l'editor_path configuré.
     *
     * @return string Chemin relatif terminant par '?' (ex: "browser/4610258811/cool.html?")
     */
    private function resolveEditorPath(): string
    {
        return Cache::remember('collabora.discovery_editor_path', 86400, function (): string {
            $internalUrl = rtrim((string) config('collabora.internal_url', 'http://127.0.0.1:9980'), '/');
            $fallback = ltrim((string) config('collabora.editor_path', '/browser/dist/cool.html'), '/').((str_contains((string) config('collabora.editor_path', ''), '?')) ? '' : '?');

            try {
                $response = Http::timeout(5)->get($internalUrl.'/hosting/discovery');

                if (! $response->ok()) {
                    return $fallback;
                }

                $xml = simplexml_load_string($response->body());

                if ($xml === false) {
                    return $fallback;
                }

                $actions = $xml->xpath('//action[@name="edit"]');

                if (empty($actions)) {
                    return $fallback;
                }

                $urlsrc = (string) $actions[0]['urlsrc'];
                $parsed = parse_url($urlsrc);

                if (empty($parsed['path'])) {
                    return $fallback;
                }

                // Retourne le chemin sans le host, terminé par '?' pour y accoler WOPISrc=
                $path = ltrim($parsed['path'], '/');

                return $path.(str_contains($path, '?') ? '' : '?');
            } catch (\Throwable) {
                return $fallback;
            }
        });
    }
}
