<?php
 
namespace App\Models\Tenant;
 
use Illuminate\Database\Eloquent\Model;
 
/**
 * Journal d'audit immuable.
 * Ne jamais permettre la modification ou suppression de ces entrées.
 */
class AuditLog extends Model
{
    protected $connection = 'tenant';
 
    public $timestamps = false; // created_at géré manuellement
 
    protected $fillable = [
        'user_id', 'user_name', 'action',
        'model_type', 'model_id',
        'old_values', 'new_values',
        'ip_address', 'user_agent',
    ];
 
    // Interdire la modification
    public function update(array $attributes = [], array $options = []): bool
    {
        return false;
    }
 
    // Interdire la suppression
    public function delete(): ?bool
    {
        return false;
    }
}
