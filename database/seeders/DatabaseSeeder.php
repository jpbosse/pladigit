<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder principal — point d'entrée pour php artisan db:seed.
 *
 * Usage courant :
 *   php artisan db:seed                          → appelle tous les seeders listés
 *   php artisan db:seed --class=PladigitProjectSeeder  → seeder spécifique
 *
 * Prérequis :
 *   - Connexion tenant active (TenantManager::connectTo() déjà appelé)
 *   - Toutes les migrations tenant appliquées
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Jeu de données de démonstration — projet Pladigit complet
        // (tâches, jalons, événements agenda — ~108 tâches, 13 jalons)
        $this->call([
            PladigitProjectSeeder::class,
        ]);
    }
}
