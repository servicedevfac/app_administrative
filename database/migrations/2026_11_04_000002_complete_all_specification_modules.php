<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 50)->nullable()->after('service');
            $table->string('office_location', 255)->nullable()->after('phone');
            $table->json('permissions')->nullable()->after('is_active');
        });

        Schema::table('material_requests', function (Blueprint $table): void {
            $table->string('urgency', 50)->default('Normale')->change();
            $table->text('service_description')->nullable()->after('motif');
            $table->foreignId('verified_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('submitted_at');
            $table->timestamp('executed_at')->nullable()->after('approved_at');
        });

        Schema::table('administrative_documents', function (Blueprint $table): void {
            $table->string('type', 50)->default('PDF')->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('from_service')->nullable()->after('to_location');
            $table->string('to_service')->nullable()->after('from_service');
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->string('registration_card_number', 100)->nullable()->after('type');
            $table->date('tax_expires_at')->nullable()->after('inspection_expires_at');
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->string('authorization_reference', 100)->nullable()->after('material_request_id');
            $table->text('overrun_reason')->nullable()->after('notes');
        });

        Schema::create('request_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('requires_stock_items')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('asset_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('severity', 30)->default('Mineur');
            $table->string('title');
            $table->text('description');
            $table->string('status', 30)->default('Ouvert');
            $table->date('incident_date');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 80);
            $table->date('issue_date')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expense_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 60)->default('Facture');
            $table->string('title');
            $table->string('file_path');
            $table->string('original_name');
            $table->string('file_size', 40)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expense_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('saved_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('type', 50);
            $table->string('title');
            $table->date('period_from');
            $table->date('period_to');
            $table->text('difficulties')->nullable();
            $table->text('action_items')->nullable();
            $table->json('filters')->nullable();
            $table->json('snapshot');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('approval_delegations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope', 80)->default('Toutes les demandes');
            $table->string('department')->nullable();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('system_backups', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('filename');
            $table->string('file_path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('tables_count')->default(0);
            $table->unsignedInteger('records_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('request_categories')->insertOrIgnore([
            ['name' => 'Papeterie et fournitures de bureau', 'requires_stock_items' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Matériel informatique et accessoires', 'requires_stock_items' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Consommables informatiques', 'requires_stock_items' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Fournitures et équipements de cuisine', 'requires_stock_items' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => "Provisions et produits d'entretien", 'requires_stock_items' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mobilier et équipements de bureau', 'requires_stock_items' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Demandes liées aux véhicules', 'requires_stock_items' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Maintenance et réparations', 'requires_stock_items' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Prestations de services', 'requires_stock_items' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Autres besoins administratifs et logistiques', 'requires_stock_items' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('departments')->insertOrIgnore([
            ['name' => 'Direction Générale', 'code' => 'DG', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ressources Humaines', 'code' => 'RH', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Administration & Finances', 'code' => 'DAF', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Logistique & Moyens Généraux', 'code' => 'LOG', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Informatique', 'code' => 'DSI', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Opérations & Commercial', 'code' => 'OPS', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('storage_locations')->insertOrIgnore([
            ['name' => 'Magasin Central - Rayon A', 'site' => 'Siège Social', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Magasin Central - Rayon B', 'site' => 'Siège Social', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Réserve Informatique', 'site' => 'Siège Social - 2e étage', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Économat & Entretien', 'site' => 'Annexe Logistique', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('article_categories')->insertOrIgnore([
            ['name' => 'Consommables informatiques', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cuisine & Économat', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Entretien & Hygiène', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mobilier de bureau', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Pièces & Accessoires Auto', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('measurement_units')->insertOrIgnore([
            ['name' => 'Carton', 'symbol' => 'ctn', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Litre', 'symbol' => 'L', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kilogramme', 'symbol' => 'kg', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lot', 'symbol' => 'lot', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Paquet', 'symbol' => 'pqt', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('system_settings')->insertOrIgnore([
            ['key' => 'company_name', 'value' => 'GESTION+ ENTERPRISE - SIÈGE SOCIAL', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_short_code', 'value' => 'GP-ENT', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_address', 'value' => "Plateau, Immeuble Horizon, Abidjan - Côte d'Ivoire", 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_phone', 'value' => '+225 27 20 00 00 00', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'company_email', 'value' => 'administration@entreprise.ci', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'default_min_threshold', 'value' => '5', 'group' => 'stocks', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'default_max_threshold', 'value' => '100', 'group' => 'stocks', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'block_zero_stock', 'value' => '1', 'group' => 'stocks', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'require_movement_reason', 'value' => '1', 'group' => 'stocks', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'validation_levels', 'value' => '2', 'group' => 'workflow', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'sla_processing_hours', 'value' => '48', 'group' => 'workflow', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'require_rejection_reason', 'value' => '1', 'group' => 'workflow', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notify_on_request_submitted', 'value' => '1', 'group' => 'notifications', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notify_on_request_decision', 'value' => '1', 'group' => 'notifications', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notify_on_low_stock', 'value' => '1', 'group' => 'notifications', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notify_on_expense_overrun', 'value' => '1', 'group' => 'notifications', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'notify_on_vehicle_alert', 'value' => '1', 'group' => 'notifications', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_backups');
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('saved_reports');
        Schema::dropIfExists('expense_audits');
        Schema::dropIfExists('expense_attachments');
        Schema::dropIfExists('vehicle_documents');
        Schema::dropIfExists('asset_incidents');
        Schema::dropIfExists('request_categories');

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropColumn(['authorization_reference', 'overrun_reason']);
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropColumn(['registration_card_number', 'tax_expires_at']);
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn(['from_service', 'to_service']);
        });

        Schema::table('material_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['service_description', 'verified_at', 'executed_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'office_location', 'permissions']);
        });
    }
};
