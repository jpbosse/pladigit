# ADR-020 — GED : abstraction du stockage via GedStorageInterface

**Date :** Avril 2026
**Statut :** Accepté

---

## Contexte

La GED doit stocker des documents sur des supports variés : disque local, SFTP (NAS Linux), SMB/CIFS (NAS Windows/Samba). Le code métier (upload, download, versioning, WOPI) ne doit pas dépendre du driver de stockage.

## Décision

Introduire `GedStorageInterface` avec trois méthodes (`exists`, `get`, `put`) et trois implémentations : `LocalGedDriver`, `SftpGedDriver`, `SmbGedDriver`. La factory `NasManager::gedDriver()` instancie le bon driver depuis `TenantSettings`. Le binding Laravel est enregistré dans `AppServiceProvider`.

Ce pattern est identique à celui de la Photothèque (`NasManager` pour les médias).

## Conséquences

- `WopiController`, `GedDocumentController`, `GedSyncService` utilisent `GedStorageInterface` injecté — zéro couplage au driver.
- Changer de stockage (local → SFTP) ne nécessite qu'une mise à jour de `TenantSettings` + migration des fichiers physiques.
- Tests : `Storage::fake('local')` suffit pour couvrir le driver local en CI sans accès réseau.
