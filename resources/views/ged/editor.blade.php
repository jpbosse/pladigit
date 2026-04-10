<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->name }} — Collabora</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            font-family: 'DM Sans', sans-serif;
            background: #1a1a2e;
        }

        #collab-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            height: 40px;
            padding: 0 16px;
            background: #0f0f23;
            border-bottom: 1px solid rgba(255,255,255,.1);
            color: #e0e0e0;
            font-size: 13px;
        }

        #collab-bar a {
            color: #8ab4f8;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        #collab-bar a:hover { text-decoration: underline; }

        #collab-sep { color: rgba(255,255,255,.3); }

        #collab-name {
            font-weight: 600;
            color: #fff;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 400px;
        }

        #collab-badge {
            margin-left: auto;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 12px;
            background: rgba(255,193,7,.15);
            color: #ffc107;
            border: 1px solid rgba(255,193,7,.3);
        }

        #collab-frame {
            width: 100%;
            height: calc(100vh - 40px);
            border: none;
            display: block;
        }
    </style>
</head>
<body>
    <div id="collab-bar">
        <a href="{{ route('ged.folders.show', $document->folder_id) }}">
            ← GED
        </a>
        <span id="collab-sep">/</span>
        <span id="collab-name" title="{{ $document->name }}">{{ $document->name }}</span>
        <span id="collab-badge">Lecture seule</span>
    </div>

    {{--
        WOPI spec : le token est envoyé via form POST vers Collabora,
        qui répond dans l'iframe nommée "collabora-online".
    --}}
    <form id="collabora-form"
          action="{{ $actionUrl }}"
          method="post"
          target="collabora-online"
          style="display:none;">
        <input type="hidden" name="access_token"     value="{{ $accessToken }}">
        <input type="hidden" name="access_token_ttl" value="{{ $ttlMs }}">
    </form>

    <iframe id="collab-frame"
            name="collabora-online"
            allow="fullscreen"
            allowfullscreen></iframe>

    <script>
        document.getElementById('collabora-form').submit();
    </script>
</body>
</html>
