<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Estilista;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EncryptSensitiveData extends Command
{
    protected $signature = 'datos:encriptar-sensibles {--dry-run : Muestra cuántos registros se actualizarían sin escribir}';

    protected $description = 'Encripta en BD los campos sensibles existentes que aún estén en texto plano';

    /** @var array<class-string<Model>, list<string>> */
    private array $targets = [
        Cliente::class => ['direccion', 'observaciones', 'preferencias_cabello', 'formula_color'],
        User::class => ['cedula'],
        Proveedor::class => ['telefono'],
        Estilista::class => ['telefono'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach ($this->targets as $modelClass => $fields) {
            $updated = 0;
            $modelClass::query()->withoutGlobalScopes()->orderBy('id')->chunkById(100, function ($rows) use ($fields, $dryRun, &$updated): void {
                foreach ($rows as $row) {
                    $dirty = false;
                    foreach ($fields as $field) {
                        $raw = $row->getRawOriginal($field);
                        if ($raw === null || $raw === '') {
                            continue;
                        }
                        if ($this->isEncrypted($raw)) {
                            continue;
                        }
                        $row->setAttribute($field, $raw);
                        $dirty = true;
                    }
                    if ($dirty) {
                        $updated++;
                        if (! $dryRun) {
                            $row->saveQuietly();
                        }
                    }
                }
            });

            $label = class_basename($modelClass);
            $this->line(sprintf('%s: %d registro(s) %s.', $label, $updated, $dryRun ? 'por encriptar' : 'encriptados'));
        }

        if ($dryRun) {
            $this->warn('Ejecución en seco: no se realizaron cambios.');
        } else {
            $this->info('Listo. Verifica que APP_KEY esté definido y respaldado; sin él no se podrán leer los datos.');
        }

        return self::SUCCESS;
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}