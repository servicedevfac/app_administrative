<?php

use App\Models\ActivityLog;
use App\Models\SystemBackup;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('backup:data', function (): void {
    $backup = SystemBackup::createSnapshot();

    ActivityLog::create([
        'user_id' => null,
        'action' => 'backup.created_automatically',
        'subject_type' => SystemBackup::class,
        'subject_id' => $backup->id,
        'description' => "Sauvegarde automatique {$backup->reference} générée ({$backup->records_count} enregistrements).",
        'metadata' => ['tables_count' => $backup->tables_count],
        'ip_address' => 'scheduler',
    ]);

    $this->info("Sauvegarde {$backup->reference} générée avec succès.");
})->purpose('Créer une sauvegarde JSON des données métier')
    ->dailyAt('03:00')
    ->withoutOverlapping();
