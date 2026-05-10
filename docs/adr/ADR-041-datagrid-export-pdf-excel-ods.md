# ADR-041 — Datagrid : export PDF, Excel et ODS

## Statut
Accepté — 2026-05-10

## Contexte
Le module Datagrid doit permettre aux agents des collectivités d'exporter leurs données
sous différents formats : PDF pour l'impression, Excel/ODS pour la manipulation bureautique.

## Décisions

### Export Excel et ODS
- Librairie : `maatwebsite/excel` (^3.1)
- Les exports respectent les colonnes visibles et les filtres actifs
- Implémentés via des méthodes Livewire (`exportExcel`, `exportOds`) dans `ShowGrid`
- La classe `DatagridExport` utilise `DB::connection('tenant')` et `$table->mysql_table`

### Export PDF
- Librairie : `barryvdh/laravel-dompdf` (^3.1), déjà présente
- Deux exports distincts :
  - **Fiche** : une seule ligne, tous les champs visibles, format A4 portrait
  - **Liste** : lignes filtrées, colonnes visibles, format A4 paysage
- Implémentés via un controller dédié `DatagridPdfController` (et non via Livewire)
  car Livewire ne peut pas retourner de `BinaryFileResponse` (sérialisation JSON incompatible)
- Les boutons dans la vue utilisent des liens `<a target="_blank">` pointant vers les routes HTTP

### Limite PDF liste : 100 lignes
- DomPDF consomme beaucoup de mémoire (~256 Mo par défaut)
- Au-delà de ~100 lignes, DomPDF lève une `FatalError: Allowed memory size exhausted`
- **Décision** : limiter le PDF liste à 100 lignes maximum
- Pour exporter un volume complet, l'utilisateur doit utiliser l'export Excel ou ODS
  (pas de limite, streaming natif)
- Cette limite est documentée dans l'interface via le libellé du bouton si nécessaire

### Encodage UTF-8
- Les données MySQL sont en `utf8mb4` mais DomPDF peut échouer sur certains caractères
- Toutes les valeurs string sont passées par `mb_convert_encoding($v, 'UTF-8', 'auto')`
  avant d'être transmises aux vues PDF

## Conséquences
- PDF : impression rapide d'une fiche ou d'un extrait filtré (≤ 100 lignes)
- Excel/ODS : export complet sans limite pour traitement bureautique
- Architecture claire : Livewire pour les exports streaming, controller HTTP pour PDF
