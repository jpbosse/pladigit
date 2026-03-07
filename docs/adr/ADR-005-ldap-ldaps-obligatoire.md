# ADR-005 — LDAP : LDAPS (TLS) obligatoire

**Date :** Janvier 2026  
**Statut :** Accepté

## Décision

Utiliser exclusivement LDAPS (LDAP over TLS) sur le port 636. La valeur `ldap_use_tls` est stockée en base avec default `true`. Il est techniquement impossible de la désactiver depuis l'interface.

## Conséquences

Certains annuaires anciens (Windows Server 2008) peuvent ne pas supporter LDAPS. Dans ce cas, l'organisation doit mettre à jour son Active Directory avant de pouvoir utiliser la synchronisation LDAP.
