<?php

namespace App\Services;

/**
 * Levée quand le provisioning d'un tenant échoue.
 *
 * Garantit que l'appelant peut distinguer cette erreur métier
 * des autres RuntimeException du système.
 *
 * Usage :
 *   try {
 *       $this->provisioning->provisionTenant($org);
 *   } catch (ProvisioningException $e) {
 *       return back()->with('error', $e->getMessage());
 *   }
 */
class ProvisioningException extends \RuntimeException {}
