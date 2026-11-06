<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SystemBackup extends Model
{
    /**
     * @var list<string>
     */
    private const BACKUP_TABLES = [
        'departments',
        'storage_locations',
        'suppliers',
        'article_categories',
        'measurement_units',
        'request_categories',
        'system_settings',
        'articles',
        'material_requests',
        'request_items',
        'request_decisions',
        'stock_movements',
        'inventory_sessions',
        'inventory_items',
        'assets',
        'asset_assignments',
        'asset_incidents',
        'vehicles',
        'vehicle_documents',
        'maintenance_records',
        'expenses',
        'expense_attachments',
        'expense_audits',
        'administrative_documents',
        'approval_delegations',
        'saved_reports',
    ];

    protected $fillable = [
        'reference',
        'filename',
        'file_path',
        'size_bytes',
        'tables_count',
        'records_count',
        'created_by',
        'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'restored_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function createSnapshot(?User $creator = null): self
    {
        $payloadTables = [];
        $recordsCount = 0;

        foreach (self::BACKUP_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $rows = DB::table($table)->get()->map(fn ($row): array => (array) $row)->all();
                $payloadTables[$table] = $rows;
                $recordsCount += count($rows);
            }
        }

        $nextNumber = (int) self::query()->max('id') + 1;
        $reference = 'BKP-'.now()->format('Y').'-'.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
        $filename = $reference.'_'.now()->format('Ymd_His').'.json';
        $filePath = "backups/{$filename}";
        $json = json_encode([
            'app' => 'GESTION+',
            'reference' => $reference,
            'generated_at' => now()->toIso8601String(),
            'generated_by' => $creator?->email ?? 'system@scheduler',
            'tables' => $payloadTables,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        Storage::disk()->put($filePath, $json);

        return self::query()->create([
            'reference' => $reference,
            'filename' => $filename,
            'file_path' => $filePath,
            'size_bytes' => strlen($json),
            'tables_count' => count($payloadTables),
            'records_count' => $recordsCount,
            'created_by' => $creator?->id,
        ]);
    }
}
