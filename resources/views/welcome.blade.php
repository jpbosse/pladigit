@php
    $appHost    = parse_url(config('app.url'), PHP_URL_HOST); // pladigit.fr ou pladigit.local
    $demoUrl    = 'https://demo.' . $appHost;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pladigit — Plateforme de Digitalisation pour Collectivités</title>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Source+Sans+3:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}.hidden{display:none!important}
        :root{--navy:#1E3A5F;--navy2:#162D4A;--gold:#C4972A;--gold2:#E8B84B;--light:#F4F6F9;--grey:#6B7A8D;--white:#FFFFFF;--text:#1A2332}
        html{scroll-behavior:smooth}
        body{font-family:'Source Sans 3',sans-serif;color:var(--text);background:var(--white);overflow-x:hidden}
        nav{position:fixed;top:0;left:0;right:0;z-index:100;background:rgba(255,255,255,0.97);border-bottom:1px solid rgba(30,58,95,0.1);backdrop-filter:blur(8px)}
        .nav-inner{max-width:1200px;margin:0 auto;padding:0 2rem;height:64px;display:flex;align-items:center;justify-content:space-between}
        .nav-logo{font-family:'Libre Baskerville',serif;font-size:1.4rem;font-weight:700;color:var(--navy);letter-spacing:-0.02em;text-decoration:none}
        .nav-logo span{color:var(--gold)}
        .nav-links{display:flex;align-items:center;gap:2rem}
        .nav-links a{font-size:0.9rem;font-weight:500;color:var(--grey);text-decoration:none;transition:color 0.2s}
        .nav-links a:hover{color:var(--navy)}
        .btn-nav{background:var(--navy)!important;color:white!important;padding:0.5rem 1.25rem;border-radius:4px;font-weight:600!important}
        .btn-source{background:transparent;color:var(--navy)!important;padding:0.5rem 1.25rem;border-radius:4px;font-weight:600;border:1px solid rgba(30,58,95,0.3);font-size:0.85rem;text-decoration:none;transition:all 0.2s;display:flex;align-items:center;gap:0.4rem}
        .btn-source::before{content:"⌥"}
        .btn-source:hover{background:var(--light);border-color:var(--navy)}
        .nav-burger{display:none;background:none;border:none;cursor:pointer;padding:6px;color:var(--navy)}
        .nav-burger-bar{display:block;width:22px;height:2px;background:currentColor;border-radius:2px;transition:all 0.25s}
        .nav-burger-bar+.nav-burger-bar{margin-top:5px}
        .nav-open .nav-burger-bar:nth-child(1){transform:translateY(7px) rotate(45deg)}
        .nav-open .nav-burger-bar:nth-child(2){opacity:0;transform:scaleX(0)}
        .nav-open .nav-burger-bar:nth-child(3){transform:translateY(-7px) rotate(-45deg)}
        @media(max-width:700px){
            .nav-burger{display:block}
            .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:rgba(255,255,255,0.98);border-bottom:1px solid rgba(30,58,95,0.12);flex-direction:column;padding:1rem 2rem 1.5rem;gap:0;box-shadow:0 8px 24px rgba(30,58,95,0.10);backdrop-filter:blur(8px)}
            .nav-links.open{display:flex}
            .nav-links a{padding:0.7rem 0;border-bottom:1px solid rgba(30,58,95,0.06);font-size:0.95rem}
            .nav-links a:last-child{border-bottom:none;padding-bottom:0}
            .btn-nav{margin-top:0.5rem;text-align:center;width:100%;border-radius:6px!important}
            .btn-source{justify-content:center}
        }
        .hero{min-height:100vh;background:var(--navy);display:flex;align-items:center;position:relative;overflow:hidden;padding-top:64px}
        .hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 70% 50%,rgba(196,151,42,0.08) 0%,transparent 70%)}
        .hero-grid{position:absolute;inset:0;opacity:0.04;background-image:linear-gradient(rgba(255,255,255,0.5) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,0.5) 1px,transparent 1px);background-size:60px 60px}
        .hero-inner{max-width:1200px;margin:0 auto;padding:6rem 2rem;position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center}
        .hero-badge{display:inline-flex;align-items:center;gap:0.5rem;background:rgba(196,151,42,0.15);border:1px solid rgba(196,151,42,0.3);color:var(--gold2);padding:0.4rem 1rem;border-radius:2rem;font-size:0.8rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;margin-bottom:1.5rem}
        .hero-title{font-family:'Libre Baskerville',serif;font-size:clamp(2.2rem,4vw,3.4rem);font-weight:700;line-height:1.2;color:white;margin-bottom:1.5rem;letter-spacing:-0.02em}
        .hero-title em{font-style:italic;color:var(--gold2)}
        .hero-subtitle{font-size:1.1rem;font-weight:300;line-height:1.7;color:rgba(255,255,255,0.7);margin-bottom:2.5rem;max-width:520px}
        .hero-actions{display:flex;gap:1rem;flex-wrap:wrap}
        .btn-primary{background:var(--gold);color:var(--navy);padding:0.85rem 2rem;border-radius:4px;font-weight:700;font-size:0.95rem;text-decoration:none;transition:all 0.2s;border:2px solid var(--gold)}
        .btn-primary:hover{background:var(--gold2);border-color:var(--gold2)}
        .btn-secondary{background:transparent;color:white;padding:0.85rem 2rem;border-radius:4px;font-weight:600;font-size:0.95rem;text-decoration:none;transition:all 0.2s;border:2px solid rgba(255,255,255,0.3)}
        .btn-secondary:hover{border-color:rgba(255,255,255,0.7)}
        .hero-card{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:12px;padding:2rem;backdrop-filter:blur(10px)}
        .hero-card-title{font-size:0.75rem;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--gold2);margin-bottom:1.5rem}
        .stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem}
        .stat-item{background:rgba(255,255,255,0.05);border-radius:8px;padding:1rem;text-align:center}
        .stat-num{font-family:'Libre Baskerville',serif;font-size:1.8rem;font-weight:700;color:white}
        .stat-label{font-size:0.75rem;color:rgba(255,255,255,0.5);margin-top:0.2rem}
        .module-list{display:flex;flex-direction:column;gap:0.5rem}
        .module-item{display:flex;align-items:center;gap:0.75rem;background:rgba(255,255,255,0.04);border-radius:6px;padding:0.6rem 0.75rem}
        .module-name{font-size:0.85rem;color:rgba(255,255,255,0.8);font-weight:500}
        .module-badge{margin-left:auto;font-size:0.7rem;font-weight:600;padding:0.2rem 0.5rem;border-radius:2rem;background:rgba(196,151,42,0.2);color:var(--gold2)}
        .section{padding:6rem 2rem}
        .section-inner{max-width:1200px;margin:0 auto}
        .section-label{font-size:0.75rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:var(--gold);margin-bottom:1rem}
        .section-title{font-family:'Libre Baskerville',serif;font-size:clamp(1.8rem,3vw,2.6rem);font-weight:700;line-height:1.25;color:var(--navy);margin-bottom:1.5rem;letter-spacing:-0.02em}
        .section-subtitle{font-size:1.05rem;line-height:1.7;color:var(--grey);max-width:600px}
        .values{background:var(--light)}
        .values-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:2rem;margin-top:3rem}
        .value-card{background:white;border-radius:8px;padding:2rem;border:1px solid rgba(30,58,95,0.08);transition:box-shadow 0.2s,transform 0.2s}
        .value-card:hover{box-shadow:0 8px 32px rgba(30,58,95,0.1);transform:translateY(-2px)}
        .value-icon{width:48px;height:48px;border-radius:8px;background:rgba(30,58,95,0.08);display:flex;align-items:center;justify-content:center;font-size:1.4rem;margin-bottom:1.25rem}
        .value-title{font-family:'Libre Baskerville',serif;font-size:1.1rem;font-weight:700;color:var(--navy);margin-bottom:0.75rem}
        .value-desc{font-size:0.9rem;line-height:1.6;color:var(--grey)}
        .modules-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.5rem;margin-top:3rem}
        .module-card{border:1px solid rgba(30,58,95,0.1);border-radius:8px;padding:1.5rem;position:relative;transition:all 0.2s}
        .module-card:hover{border-color:var(--navy);box-shadow:0 4px 20px rgba(30,58,95,0.08)}
        .module-card-icon{font-size:1.8rem;margin-bottom:1rem}
        .module-card-name{font-family:'Libre Baskerville',serif;font-size:1rem;font-weight:700;color:var(--navy);margin-bottom:0.4rem}
        .module-card-desc{font-size:0.82rem;color:var(--grey);line-height:1.5}
        .module-phase{position:absolute;top:1rem;right:1rem;font-size:0.65rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--grey);background:var(--light);padding:0.2rem 0.5rem;border-radius:2rem}
        .pricing{background:var(--navy)}
        .pricing .section-title{color:white}
        .pricing .section-subtitle{color:rgba(255,255,255,0.6)}
        .pricing .section-label{color:var(--gold2)}
        .plans-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem;margin-top:3rem;max-width:760px;margin-left:auto;margin-right:auto}
        .plan-card{background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);border-radius:8px;padding:2rem;transition:all 0.2s}
        .plan-card.featured{background:white;border-color:white}
        .plan-name{font-size:0.75rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.5);margin-bottom:0.75rem}
        .plan-card.featured .plan-name{color:var(--gold)}
        .plan-price{font-family:'Libre Baskerville',serif;font-size:2rem;font-weight:700;color:white;margin-bottom:0.25rem}
        .plan-card.featured .plan-price{color:var(--navy)}
        .plan-period{font-size:0.8rem;color:rgba(255,255,255,0.4);margin-bottom:1.5rem}
        .plan-card.featured .plan-period{color:var(--grey)}
        .plan-users{font-size:0.85rem;font-weight:600;color:var(--gold2);margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid rgba(255,255,255,0.1)}
        .plan-card.featured .plan-users{color:var(--navy);border-color:rgba(30,58,95,0.1)}
        .plan-features{list-style:none;display:flex;flex-direction:column;gap:0.6rem}
        .plan-features li{font-size:0.82rem;color:rgba(255,255,255,0.65);display:flex;align-items:flex-start;gap:0.5rem}
        .plan-card.featured .plan-features li{color:var(--grey)}
        .plan-features li::before{content:'✓';color:var(--gold2);font-weight:700;flex-shrink:0}
        .contact{background:var(--light)}
        .contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:start;margin-top:3rem}
        .contact-info p{font-size:0.95rem;line-height:1.7;color:var(--grey);margin-bottom:1.5rem}
        .contact-detail{display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem}
        .contact-detail-icon{width:36px;height:36px;border-radius:6px;background:var(--navy);color:white;display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0}
        .contact-detail-text{font-size:0.9rem;color:var(--text);font-weight:500}
        .contact-form{background:white;border-radius:8px;padding:2rem;border:1px solid rgba(30,58,95,0.08)}
        .form-group{margin-bottom:1.25rem}
        .form-label{display:block;font-size:0.82rem;font-weight:600;color:var(--navy);margin-bottom:0.4rem}
        .form-input,.form-select,.form-textarea{width:100%;padding:0.65rem 0.875rem;border:1px solid rgba(30,58,95,0.2);border-radius:4px;font-family:'Source Sans 3',sans-serif;font-size:0.9rem;color:var(--text);background:white;transition:border-color 0.2s;outline:none}
        .form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--navy)}
        .form-textarea{resize:vertical;min-height:100px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .btn-submit{width:100%;padding:0.85rem;background:var(--navy);color:white;border:none;border-radius:4px;font-family:'Source Sans 3',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:background 0.2s}
        .btn-submit:hover{background:var(--navy2)}
        footer{background:var(--navy2);padding:3rem 2rem 1.5rem}
        .footer-inner{max-width:1200px;margin:0 auto}
        .footer-top{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:2rem;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:1.5rem}
        .footer-logo{font-family:'Libre Baskerville',serif;font-size:1.2rem;font-weight:700;color:white;margin-bottom:0.5rem}
        .footer-logo span{color:var(--gold)}
        .footer-tagline{font-size:0.82rem;color:rgba(255,255,255,0.4)}
        .footer-links{display:flex;gap:3rem}
        .footer-col-title{font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.3);margin-bottom:0.75rem}
        .footer-col a{display:block;font-size:0.82rem;color:rgba(255,255,255,0.55);text-decoration:none;margin-bottom:0.4rem;transition:color 0.2s}
        .footer-col a:hover{color:white}
        .footer-bottom{display:flex;justify-content:space-between;align-items:center}

        .footer-copy{font-size:0.78rem;color:rgba(255,255,255,0.3)}
        .footer-legal{display:flex;gap:1.5rem}
        .footer-legal a{font-size:0.78rem;color:rgba(255,255,255,0.3);text-decoration:none}
        .alert-success{background:#D1FAE5;border:1px solid #6EE7B7;color:#065F46;padding:1rem 2rem;text-align:center;font-weight:500}
        .fade-up{opacity:0;transform:translateY(24px);transition:opacity 0.6s ease,transform 0.6s ease}
        .fade-up.visible{opacity:1;transform:translateY(0)}
        @media(max-width:900px){.hero-inner{grid-template-columns:1fr}.hero-card{display:none}.values-grid,.modules-grid{grid-template-columns:repeat(2,1fr)}.plans-grid{grid-template-columns:1fr}.contact-grid{grid-template-columns:1fr}.footer-links{gap:1.5rem}}
        @media(max-width:600px){.values-grid,.modules-grid,.plans-grid{grid-template-columns:1fr}.footer-top{flex-direction:column;gap:2rem}}
    </style>
</head>
<body>

@if(session('contact_success'))
<div class="alert-success">{{ session('contact_success') }}</div>
@endif

<nav id="main-nav">
    <div class="nav-inner">
        <a href="/" class="nav-logo">Pladi<span>git</span></a>
        <div class="nav-links" id="nav-links">
            <a href="#fonctionnalites">Fonctionnalités</a>
            <a href="#installation">Installation</a>
            <a href="#demo">Démo</a>
            <a href="#tarifs">Tarifs</a>
            <a href="#contact">Contact</a>
            <a href="https://github.com/jpbosse/pladigit" target="_blank" class="btn-source">Source</a>
            <button onclick="openLoginModal()" class="btn-nav" style="cursor:pointer;border:none">Connexion</button>
        </div>
        <button class="nav-burger" id="nav-burger" aria-label="Menu" aria-expanded="false">
            <span class="nav-burger-bar"></span>
            <span class="nav-burger-bar"></span>
            <span class="nav-burger-bar"></span>
        </button>
    </div>
</nav>

<section class="hero">
    <div class="hero-grid"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-badge">🏛 Conçu pour les collectivités publiques</div>
            <h1 class="hero-title">La plateforme <em>digitale</em><br>de votre collectivité</h1>
            <p class="hero-subtitle">Pladigit centralise la gestion documentaire, les médias, les projets et la communication interne dans un espace sécurisé et souverain.</p>
            <div class="hero-actions">
                <a href="#demo" class="btn-primary">🚀 Tester en ligne</a>
                <a href="#fonctionnalites" class="btn-secondary">Découvrir les modules</a>
            </div>
        </div>
        <div class="hero-card">
            <div class="hero-card-title">Plateforme en chiffres</div>
            <div class="stat-grid">
                <div class="stat-item"><div class="stat-num">8</div><div class="stat-label">Modules métier</div></div>
                <div class="stat-item"><div class="stat-num">100%</div><div class="stat-label">Open source</div></div>
                <div class="stat-item"><div class="stat-num">RGPD</div><div class="stat-label">Conforme</div></div>
                <div class="stat-item"><div class="stat-num">2FA</div><div class="stat-label">Sécurisé</div></div>
            </div>
            <div class="module-list">
                <div class="module-item"><span>📁</span><span class="module-name">GED — Gestion documentaire</span><span class="module-badge">Phase 3</span></div>
                <div class="module-item"><span>🖼</span><span class="module-name">Photothèque</span><span class="module-badge">Phase 3</span></div>
                <div class="module-item"><span>💬</span><span class="module-name">Messagerie interne</span><span class="module-badge">Phase 7</span></div>
            </div>
        </div>
    </div>
</section>

<section class="section values" id="presentation">
    <div class="section-inner">
        <div class="fade-up">
            <div class="section-label">Pourquoi Pladigit</div>
            <h2 class="section-title">Une plateforme pensée<br>pour le service public</h2>
            <p class="section-subtitle">Chaque organisation dispose de son propre espace isolé, sécurisé et personnalisé. Vos données restent en France, sur vos serveurs.</p>
        </div>
        <div class="values-grid">
            <div class="value-card fade-up"><div class="value-icon">🔒</div><div class="value-title">Souveraineté des données</div><p class="value-desc">Hébergement sur vos propres serveurs ou VPS français. Aucune donnée transmise à des tiers. Architecture multi-tenant avec base dédiée par organisation.</p></div>
            <div class="value-card fade-up"><div class="value-icon">🏛</div><div class="value-title">Conçu pour les collectivités</div><p class="value-desc">Gestion des rôles adaptée au secteur public (Président, DGS, Responsables). Conforme RGPD et RGAA accessibilité.</p></div>
            <div class="value-card fade-up"><div class="value-icon">🌐</div><div class="value-title">100% Open Source</div><p class="value-desc">Code source disponible sous licence AGPL-3.0. Auditable, modifiable, sans dépendance propriétaire. Construit sur Laravel.</p></div>
            <div class="value-card fade-up"><div class="value-icon">🔑</div><div class="value-title">Authentification avancée</div><p class="value-desc">Connexion locale ou via votre annuaire LDAP / Active Directory existant. Double authentification TOTP pour tous les comptes.</p></div>
            <div class="value-card fade-up"><div class="value-icon">📊</div><div class="value-title">Multi-organisations</div><p class="value-desc">Une seule installation pour gérer plusieurs collectivités. Chaque tenant est totalement isolé avec sa propre base de données.</p></div>
            <div class="value-card fade-up"><div class="value-icon">🤝</div><div class="value-title">Support local</div><p class="value-desc">Développé et maintenu par Les Bézots, association de Soullans (Vendée). Proximité, réactivité et ancrage territorial.</p></div>
        </div>
    </div>
</section>

<section class="section" id="fonctionnalites">
    <div class="section-inner">
        <div class="fade-up">
            <div class="section-label">Modules</div>
            <h2 class="section-title">Tout ce dont votre<br>organisation a besoin</h2>
            <p class="section-subtitle">8 modules métier développés progressivement. Activez uniquement ce dont vous avez besoin.</p>
        </div>
        <div class="modules-grid">
            @php $modules = [['icon'=>'📁','name'=>'GED','desc'=>'Gestion documentaire avec versioning et droits granulaires','phase'=>3],['icon'=>'🖼','name'=>'Photothèque','desc'=>'Albums, médias NAS, watermark automatique et IA Vision','phase'=>3],['icon'=>'✅','name'=>'Projets','desc'=>'Gestion de projets, tâches et suivi d\'avancement','phase'=>5],['icon'=>'📅','name'=>'Agenda','desc'=>'Calendrier partagé et gestion d\'événements','phase'=>6],['icon'=>'💬','name'=>'Messagerie','desc'=>'Chat interne temps réel par canaux thématiques','phase'=>7],['icon'=>'📊','name'=>'Sondages','desc'=>'Formulaires, votes et enquêtes internes','phase'=>8],['icon'=>'🗄','name'=>'ERP','desc'=>'Tables de données métier configurables','phase'=>9],['icon'=>'📰','name'=>'Actualités','desc'=>'Agrégateur de flux RSS et veille informationnelle','phase'=>10]]; @endphp
            @foreach($modules as $m)
            <div class="module-card fade-up">
                <div class="module-phase">Phase {{ $m['phase'] }}</div>
                <div class="module-card-icon">{{ $m['icon'] }}</div>
                <div class="module-card-name">{{ $m['name'] }}</div>
                <p class="module-card-desc">{{ $m['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ── Section Installation ─────────────────────────────────── --}}
<section class="section" id="installation" style="background:var(--navy)">
    <div class="section-inner">
        <div class="fade-up" style="text-align:center;margin-bottom:3rem">
            <div class="section-label" style="color:var(--gold2)">Mise en œuvre</div>
            <h2 class="section-title" style="color:white">Installer Pladigit<br>sur votre serveur</h2>
            <p class="section-subtitle" style="color:rgba(255,255,255,.6);margin:0 auto">
                Pladigit s'installe sur votre propre serveur Linux — vos données restent chez vous, en France.
            </p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;max-width:900px;margin:0 auto" class="fade-up">

            {{-- Colonne 1 : Installation automatique --}}
            <div style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:2rem">
                <div style="font-size:1.5rem;margin-bottom:.875rem">🚀</div>
                <div style="font-weight:700;font-size:1.05rem;color:white;margin-bottom:.5rem">Installation automatique</div>
                <p style="font-size:.875rem;color:rgba(255,255,255,.6);line-height:1.6;margin-bottom:1.5rem">
                    Deux étapes suffisent. Aucune connaissance technique requise.
                </p>

                <div style="display:flex;flex-direction:column;gap:.875rem;margin-bottom:1.75rem">
                    <div style="display:flex;gap:.875rem;align-items:flex-start">
                        <div style="width:28px;height:28px;border-radius:50%;background:var(--gold);color:var(--navy);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0">1</div>
                        <div>
                            <div style="font-size:.875rem;font-weight:600;color:white;margin-bottom:.2rem">Installez Ubuntu Server</div>
                            <div style="font-size:.8rem;color:rgba(255,255,255,.5)">Ubuntu 22.04 ou 24.04 LTS — téléchargeable gratuitement sur ubuntu.com</div>
                        </div>
                    </div>
                    <div style="display:flex;gap:.875rem;align-items:flex-start">
                        <div style="width:28px;height:28px;border-radius:50%;background:var(--gold);color:var(--navy);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;flex-shrink:0">2</div>
                        <div>
                            <div style="font-size:.875rem;font-weight:600;color:white;margin-bottom:.2rem">Copiez cette commande dans le terminal</div>
                            <div style="font-size:.8rem;color:rgba(255,255,255,.5)">Le script installe tout automatiquement et ouvre l'assistant de configuration dans votre navigateur.</div>
                        </div>
                    </div>
                </div>

                {{-- Commande à copier --}}
                <div style="background:rgba(0,0,0,.4);border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:.875rem 1rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1.25rem">
                    <code id="install-cmd" style="font-size:.78rem;color:#86efac;background:transparent;padding:0;word-break:break-all">curl -fsSL https://pladigit.fr/install.sh | sudo bash</code>
                    <button onclick="copyCmd()" id="copy-btn"
                        style="flex-shrink:0;background:var(--gold);color:var(--navy);border:none;border-radius:4px;padding:.35rem .75rem;font-size:.75rem;font-weight:700;cursor:pointer;transition:all .2s;white-space:nowrap">
                        📋 Copier
                    </button>
                </div>

                <div style="font-size:.75rem;color:rgba(255,255,255,.35);line-height:1.5">
                    ⚠️ Ne lancez pas cette commande si Pladigit est déjà installé sur ce serveur.
                </div>
            </div>

            {{-- Colonne 2 : Téléchargement manuel --}}
            <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:2rem">
                <div style="font-size:1.5rem;margin-bottom:.875rem">📥</div>
                <div style="font-weight:700;font-size:1.05rem;color:white;margin-bottom:.5rem">Téléchargement manuel</div>
                <p style="font-size:.875rem;color:rgba(255,255,255,.6);line-height:1.6;margin-bottom:1.75rem">
                    Pour les administrateurs expérimentés qui souhaitent contrôler chaque étape de l'installation.
                </p>

                <div style="display:flex;flex-direction:column;gap:.75rem;margin-bottom:1.75rem">
                    <a href="/install.sh" download
                        style="display:flex;align-items:center;gap:.75rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:.875rem 1rem;text-decoration:none;transition:all .2s"
                        onmouseover="this.style.background='rgba(255,255,255,.1)'"
                        onmouseout="this.style.background='rgba(255,255,255,.06)'">
                        <span style="font-size:1.25rem">📄</span>
                        <div>
                            <div style="font-size:.875rem;font-weight:600;color:white">install.sh</div>
                            <div style="font-size:.75rem;color:rgba(255,255,255,.4)">Script d'installation bash</div>
                        </div>
                        <span style="margin-left:auto;font-size:.75rem;color:rgba(255,255,255,.3)">↓</span>
                    </a>

                    <a href="/pladigit-wizard.txt" download="index.php"
                        style="display:flex;align-items:center;gap:.75rem;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:.875rem 1rem;text-decoration:none;transition:all .2s"
                        onmouseover="this.style.background='rgba(255,255,255,.1)'"
                        onmouseout="this.style.background='rgba(255,255,255,.06)'">
                        <span style="font-size:1.25rem">🌐</span>
                        <div>
                            <div style="font-size:.875rem;font-weight:600;color:white">install/index.php</div>
                            <div style="font-size:.75rem;color:rgba(255,255,255,.4)">Assistant de configuration web</div>
                        </div>
                        <span style="margin-left:auto;font-size:.75rem;color:rgba(255,255,255,.3)">↓</span>
                    </a>
                </div>

                <div style="font-size:.75rem;color:rgba(255,255,255,.35);line-height:1.5">
                    Placez <code style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.7)">install.sh</code> à la racine de votre projet et
                    <code style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.7)">index.php</code> dans un dossier <code style="background:rgba(255,255,255,.1);color:rgba(255,255,255,.7)">install/</code>.
                </div>
            </div>
        </div>

        {{-- Liens documentation --}}
        <div class="fade-up" style="text-align:center;margin-top:2.5rem;display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:center">
            <a href="https://jpbosse.github.io/pladigit/GUIDE-INSTALLATION.html" target="_blank" rel="noopener"
                style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:6px;padding:.65rem 1.25rem;font-size:.875rem;font-weight:600;text-decoration:none;transition:all .2s;backdrop-filter:blur(4px)"
                onmouseover="this.style.background='rgba(255,255,255,.25)';this.style.borderColor='rgba(255,255,255,.7)'"
                onmouseout="this.style.background='rgba(255,255,255,.15)';this.style.borderColor='rgba(255,255,255,.4)'">
                📖 Guide d'installation illustré
            </a>
            <a href="https://github.com/jpbosse/pladigit/blob/main/INSTALL.md" target="_blank" rel="noopener"
                style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:6px;padding:.65rem 1.25rem;font-size:.875rem;font-weight:600;text-decoration:none;transition:all .2s;backdrop-filter:blur(4px)"
                onmouseover="this.style.background='rgba(255,255,255,.25)';this.style.borderColor='rgba(255,255,255,.7)'"
                onmouseout="this.style.background='rgba(255,255,255,.15)';this.style.borderColor='rgba(255,255,255,.4)'">
                ⚙️ Documentation technique
            </a>
            <a href="https://github.com/jpbosse/pladigit" target="_blank" rel="noopener"
                style="display:inline-flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.4);border-radius:6px;padding:.65rem 1.25rem;font-size:.875rem;font-weight:600;text-decoration:none;transition:all .2s;backdrop-filter:blur(4px)"
                onmouseover="this.style.background='rgba(255,255,255,.25)';this.style.borderColor='rgba(255,255,255,.7)'"
                onmouseout="this.style.background='rgba(255,255,255,.15)';this.style.borderColor='rgba(255,255,255,.4)'">
                ⭐ Code source GitHub
            </a>
        </div>
    </div>
</section>

<section class="section" id="demo" style="background:var(--light)">
    <div class="section-inner">
        <div class="fade-up" style="text-align:center;margin-bottom:3rem">
            <div class="section-label">Instance publique</div>
            <h2 class="section-title">Testez Pladigit maintenant</h2>
            <p class="section-subtitle" style="margin:0 auto 2rem">Une instance de démonstration est disponible 24h/24, 7j/7. Elle est réinitialisée toutes les 2 heures.</p>
            <a href="{{ $demoUrl }}/login" target="_blank"
               style="display:inline-flex;align-items:center;gap:0.5rem;background:var(--gold);color:var(--navy);padding:0.85rem 2.5rem;border-radius:4px;font-weight:700;font-size:1rem;text-decoration:none;transition:all 0.2s;border:2px solid var(--gold)"
               onmouseover="this.style.background='var(--gold2)';this.style.borderColor='var(--gold2)'"
               onmouseout="this.style.background='var(--gold)';this.style.borderColor='var(--gold)'">
                🚀 Accéder à la démo
            </a>
        </div>

        <div class="fade-up" style="max-width:760px;margin:0 auto">
            <div style="background:white;border-radius:8px;border:1px solid rgba(30,58,95,0.1);overflow:hidden">
                <div style="background:var(--navy);padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between">
                    <span style="font-size:0.75rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--gold2)">Comptes disponibles — mot de passe : <code style="background:rgba(255,255,255,0.1);padding:0.15rem 0.4rem;border-radius:3px;font-size:0.8rem;text-transform:none;letter-spacing:0">demo1234</code></span>
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:0.875rem">
                    <thead>
                        <tr style="border-bottom:1px solid rgba(30,58,95,0.08)">
                            <th style="padding:0.75rem 1.25rem;text-align:left;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--grey)">Rôle</th>
                            <th style="padding:0.75rem 1.25rem;text-align:left;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--grey)">Nom</th>
                            <th style="padding:0.75rem 1.25rem;text-align:left;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--grey)">Email</th>
                            <th style="padding:0.75rem 1.25rem;text-align:center;font-size:0.72rem;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--grey)">Accès direct</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                        $demoAccounts = [
                            ['role' => 'Administrateur',   'name' => 'Isabelle Fontaine',   'email' => 'admin@demo.pladigit.fr'],
                            ['role' => 'Maire',            'name' => 'Jean-Marie Lebreton', 'email' => 'maire@demo.pladigit.fr'],
                            ['role' => 'DGS',              'name' => 'Sophie Marchand',     'email' => 'dgs@demo.pladigit.fr'],
                            ['role' => 'Resp. Direction',  'name' => 'Thomas Girard',       'email' => 'technique@demo.pladigit.fr'],
                            ['role' => 'Resp. Service',    'name' => 'Laurent Dubois',      'email' => 'urbanisme@demo.pladigit.fr'],
                            ['role' => 'Resp. Service',    'name' => 'Nathalie Petit',      'email' => 'communication@demo.pladigit.fr'],
                            ['role' => 'Agent',            'name' => 'Éric Moreau',         'email' => 'agent1@demo.pladigit.fr'],
                            ['role' => 'Agent',            'name' => 'Marie-Claire Aubert', 'email' => 'agent2@demo.pladigit.fr'],
                        ];
                        @endphp
                        @foreach($demoAccounts as $i => $account)
                        <tr style="border-bottom:1px solid rgba(30,58,95,0.06);{{ $i % 2 === 1 ? 'background:rgba(30,58,95,0.02)' : '' }}">
                            <td style="padding:0.75rem 1.25rem;font-weight:600;color:var(--navy)">{{ $account['role'] }}</td>
                            <td style="padding:0.75rem 1.25rem;color:var(--text)">{{ $account['name'] }}</td>
                            <td style="padding:0.75rem 1.25rem"><code style="font-size:0.8rem;color:var(--grey);background:var(--light);padding:0.15rem 0.4rem;border-radius:3px">{{ $account['email'] }}</code></td>
                            <td style="padding:0.75rem 1.25rem;text-align:center">
                                <a href="{{ $demoUrl }}/login?email={{ urlencode($account['email']) }}" target="_blank"
                                   style="font-size:0.75rem;font-weight:600;color:var(--navy);text-decoration:none;border:1px solid rgba(30,58,95,0.2);padding:0.25rem 0.6rem;border-radius:3px;transition:all 0.2s"
                                   onmouseover="this.style.background='var(--navy)';this.style.color='white'"
                                   onmouseout="this.style.background='transparent';this.style.color='var(--navy)'">
                                    Connexion →
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<section class="section pricing" id="tarifs">
    <div class="section-inner">
        <div class="fade-up">
            <div class="section-label">Tarifs</div>
            <h2 class="section-title">Des plans adaptés<br>à chaque collectivité</h2>
            <p class="section-subtitle">Tous les plans incluent le socle d'authentification, la gestion des utilisateurs et l'hébergement sécurisé.</p>
        </div>
        <div class="plans-grid">
	@php $plans = [
    [
        'name'     => 'Communautaire',
        'price'    => '0 €',
        'period'   => '/ mois',
        'users'    => 'Utilisateurs illimités',
        'features' => [
            'Tous les modules inclus',
            'Authentification LDAP + 2FA',
            'Multi-organisations',
            'Auto-hébergé — vos serveurs',
            'Code source AGPL-3.0',
        ],
        'featured' => false,
    ],
    [
        'name'     => 'Partenaire',
        'price'    => 'Sur devis',
        'period'   => '',
        'users'    => 'Utilisateurs illimités',
        'features' => [
            'Tout Communautaire inclus',
            'Personnalisation visuelle et fonctionnelle',
            'Support dédié par email et téléphone',
            'Formation des utilisateurs et administrateurs',
            'Maintenance et mises à jour incluses',
        ],
        'featured' => true,
    ],
]; @endphp
            @foreach($plans as $plan)
            <div class="plan-card {{ $plan['featured'] ? 'featured' : '' }} fade-up">
                <div class="plan-name">{{ $plan['name'] }}</div>
                <div class="plan-price">{{ $plan['price'] }}</div>
                <div class="plan-period">{{ $plan['period'] }}</div>
                <div class="plan-users">{{ $plan['users'] }}</div>
                <ul class="plan-features">@foreach($plan['features'] as $f)<li>{{ $f }}</li>@endforeach</ul>
            </div>
            @endforeach
        </div>
    </div>
</section>

<section class="section contact" id="contact">
    <div class="section-inner">
        <div class="fade-up">
            <div class="section-label">Contact</div>
            <h2 class="section-title">Demandez une démonstration</h2>
        </div>
        <div class="contact-grid">
            <div class="contact-info fade-up">
                <p>Vous souhaitez découvrir Pladigit pour votre collectivité ? Notre équipe est disponible pour vous présenter la plateforme et répondre à vos questions.</p>
                <div class="contact-detail"><div class="contact-detail-icon">📍</div><div class="contact-detail-text">Les Bézots — Soullans (85300), Vendée</div></div>
                <div class="contact-detail"><div class="contact-detail-icon">✉</div><div class="contact-detail-text">contact@lesbezots.fr</div></div>
                <div class="contact-detail"><div class="contact-detail-icon">⚖</div><div class="contact-detail-text">Licence AGPL-3.0 — Code source sur GitHub</div></div>
            </div>
            <div class="contact-form fade-up">
                <form method="POST" action="{{ route('contact.send') }}">
                    @csrf
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Prénom *</label><input type="text" name="first_name" class="form-input" required placeholder="Marie"></div>
                        <div class="form-group"><label class="form-label">Nom *</label><input type="text" name="last_name" class="form-input" required placeholder="Dupont"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Organisation *</label><input type="text" name="organization" class="form-input" required placeholder="Communauté de Communes de l'île de Noirmoutier"></div>
                    <div class="form-group"><label class="form-label">Email professionnel *</label><input type="email" name="email" class="form-input" required placeholder="m.dupont@collectivite.fr"></div>
                    <div class="form-group"><label class="form-label">Plan souhaité</label><select name="plan" class="form-select">
<option value="">-- Choisir --</option>
<option value="communautaire">Communautaire — Gratuit</option>
<option value="partenaire">Partenaire — Sur devis</option>
</select></div>
                    <div class="form-group"><label class="form-label">Message</label><textarea name="message" class="form-textarea" placeholder="Décrivez votre besoin..."></textarea></div>
                    <button type="submit" class="btn-submit">Envoyer la demande</button>
                </form>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="footer-inner">
        <div class="footer-top">
            <div>
                <div class="footer-logo">Pladi<span>git</span></div>
                <div class="footer-tagline">Plateforme de Digitalisation Interne<br>pour les Collectivités Publiques</div>
            </div>
            <div class="footer-links">
                <div class="footer-col"><div class="footer-col-title">Plateforme</div><a href="#fonctionnalites">Fonctionnalités</a>
            <a href="#installation">Installation</a><a href="#tarifs">Tarifs</a><a href="#demo">Démo</a></div>
                <div class="footer-col"><div class="footer-col-title">Ressources</div><a href="#">Documentation</a><a href="#">GitHub</a><a href="#">Changelog</a></div>
                <div class="footer-col"><div class="footer-col-title">Les Bézots</div><a href="#">À propos</a><a href="#contact">Contact</a><a href="#">Mentions légales</a></div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="footer-copy">© {{ date('Y') }} Les Bézots — Soullans (85), France — Licence AGPL-3.0</div>
            <div class="footer-legal"><a href="#">Mentions légales</a><a href="#">Confidentialité</a><a href="#">CGU</a></div>
        </div>
    </div>
</footer>


{{-- ── Modal de connexion ─────────────────────────────────── --}}
<div id="login-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(15,25,40,0.7);backdrop-filter:blur(4px);align-items:center;justify-content:center">
    <div style="background:white;border-radius:12px;padding:2.5rem;width:100%;max-width:420px;margin:1rem;box-shadow:0 24px 64px rgba(0,0,0,0.25);position:relative">

        {{-- Fermeture --}}
        <button onclick="closeLoginModal()" style="position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.25rem;color:#9ca3af;cursor:pointer;line-height:1">✕</button>

        {{-- En-tête --}}
        <div style="margin-bottom:1.75rem">
            <div style="font-family:'Libre Baskerville',serif;font-size:1.3rem;font-weight:700;color:#1E3A5F;margin-bottom:0.25rem">Connexion à Pladigit</div>
            <div style="font-size:0.82rem;color:#6B7A8D">Saisissez l'identifiant de votre organisation pour accéder à votre espace.</div>
        </div>

        {{-- Champ Organisation --}}
        <div style="margin-bottom:1.5rem">
            <label style="display:block;font-size:0.78rem;font-weight:600;color:#1E3A5F;margin-bottom:0.35rem">
                Identifiant de l'organisation
                <span style="font-weight:400;color:#9ca3af;font-size:0.72rem;margin-left:0.4rem">fourni par votre administrateur</span>
            </label>
            <input id="modal-org" type="text" placeholder="ex: mairie-soullans" autocomplete="organization"
                style="width:100%;padding:0.65rem 0.875rem;border:1px solid #d1d5db;border-radius:4px;font-size:0.9rem;font-family:'Source Sans 3',sans-serif;outline:none;transition:border-color 0.2s"
                onfocus="this.style.borderColor='#1E3A5F'" onblur="this.style.borderColor='#d1d5db'"
                onkeydown="if(event.key==='Enter')submitLogin()">
        </div>

        {{-- Message d'erreur --}}
        <div id="modal-error" style="display:none;background:#FEF2F2;border:1px solid #FECACA;border-radius:4px;padding:0.65rem 0.875rem;font-size:0.82rem;color:#DC2626;margin-bottom:1rem"></div>

        {{-- Bouton connexion --}}
        <button id="modal-btn" onclick="submitLogin()"
            style="width:100%;padding:0.85rem;background:#1E3A5F;color:white;border:none;border-radius:4px;font-family:'Source Sans 3',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:background 0.2s"
            onmouseover="this.style.background='#162D4A'" onmouseout="this.style.background='#1E3A5F'">
            Accéder à mon espace →
        </button>

        {{-- Aide --}}
        <p style="font-size:0.72rem;color:#9ca3af;text-align:center;margin-top:1rem">
            Problème de connexion ? Contactez votre administrateur.
        </p>
    </div>
</div>

<script>

// ── Copier la commande d'installation ───────────────────────
function copyCmd() {
    var cmd = document.getElementById('install-cmd').textContent;
    var btn = document.getElementById('copy-btn');
    navigator.clipboard.writeText(cmd).then(function() {
        btn.textContent = '✅ Copié !';
        setTimeout(function() { btn.textContent = '📋 Copier'; }, 2000);
    }).catch(function() {
        // Fallback pour les navigateurs sans clipboard API
        var ta = document.createElement('textarea');
        ta.value = cmd;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        btn.textContent = '✅ Copié !';
        setTimeout(function() { btn.textContent = '📋 Copier'; }, 2000);
    });
}

// ── Burger menu mobile ───────────────────────────────────────
(function(){
    var burger = document.getElementById('nav-burger');
    var links  = document.getElementById('nav-links');
    var nav    = document.getElementById('main-nav');
    if (!burger || !links) return;
    burger.addEventListener('click', function(){
        var open = links.classList.toggle('open');
        nav.classList.toggle('nav-open', open);
        burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    // Fermer si clic sur un lien
    links.querySelectorAll('a, button').forEach(function(el){
        el.addEventListener('click', function(){
            links.classList.remove('open');
            nav.classList.remove('nav-open');
            burger.setAttribute('aria-expanded', 'false');
        });
    });
})();

// ── Helpers cookie ───────────────────────────────────────────
function setCookie(name, value, days) {
    var d = new Date();
    d.setTime(d.getTime() + days * 864e5);
    document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
}
function getCookie(name) {
    var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : '';
}

// ── Modal ────────────────────────────────────────────────────
function openLoginModal() {
    var savedOrg = getCookie('pladigit_org');
    if (savedOrg) {
        document.getElementById('modal-org').value = savedOrg;
    }
    document.getElementById('modal-error').style.display = 'none';
    document.getElementById('login-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(function() { document.getElementById('modal-org').focus(); }, 100);
}
function closeLoginModal() {
    document.getElementById('login-modal').style.display = 'none';
    document.body.style.overflow = '';
}
document.getElementById('login-modal').addEventListener('click', function(e) {
    if (e.target === this) closeLoginModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLoginModal();
});

// ── Soumission : vérifier org puis rediriger vers slug.pladigit.fr/login ──
function submitLogin() {
    var slug = document.getElementById('modal-org').value.trim().toLowerCase();
    var btn  = document.getElementById('modal-btn');
    var err  = document.getElementById('modal-error');

    err.style.display = 'none';
    if (!slug) {
        err.textContent = 'Veuillez saisir l\'identifiant de votre organisation.';
        err.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.textContent = '⏳ Vérification...';

    fetch('/check-org-ajax/' + encodeURIComponent(slug))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.exists) {
                err.textContent = '❌ Organisation « ' + slug + ' » introuvable. Vérifiez l\'identifiant.';
                err.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Accéder à mon espace →';
                return;
            }
            // Mémoriser l'org pour la prochaine visite
            setCookie('pladigit_org', slug, 365);
            // Rediriger vers la page de login du tenant
            // L'email sera saisi directement sur la page du tenant, avec son propre token CSRF
            window.location.href = 'https://' + slug + '.{{ $appHost }}/login';
        })
        .catch(function() {
            err.textContent = 'Erreur réseau. Vérifiez votre connexion et réessayez.';
            err.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Accéder à mon espace →';
        });
}
</script>

<script>
const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
        if (entry.isIntersecting) {
            setTimeout(() => entry.target.classList.add('visible'), i * 80);
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });
document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
</script>

</body>
</html>
