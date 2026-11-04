<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 50)->default('collaborator')->change();
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('deactivated_at')->nullable()->after('last_login_at');
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->unsignedInteger('maximum_threshold')->nullable()->after('threshold');
            $table->decimal('unit_cost', 15, 2)->nullable()->after('maximum_threshold');
            $table->string('supplier')->nullable()->after('unit_cost');
        });

        Schema::table('material_requests', function (Blueprint $table): void {
            $table->string('category')->default('Autres besoins administratifs et logistiques')->after('type_operation');
            $table->foreignId('assigned_to')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable()->after('delivered_at');
            $table->text('cancellation_reason')->nullable()->after('correction_reason');
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->string('type', 50)->change();
            $table->string('from_location')->nullable()->after('quantity');
            $table->string('to_location')->nullable()->after('from_location');
            $table->boolean('is_emergency')->default(false)->after('to_location');
            $table->string('validation_status', 30)->default('Validé')->after('is_emergency');
            $table->text('justification')->nullable()->after('reason');
        });

        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 30)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('storage_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('site')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('inventory_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('scope')->default('Général');
            $table->string('location')->nullable();
            $table->string('status', 30)->default('Brouillon');
            $table->date('inventory_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->integer('theoretical_quantity');
            $table->integer('physical_quantity');
            $table->integer('variance');
            $table->text('justification')->nullable();
            $table->timestamps();
            $table->unique(['inventory_session_id', 'article_id']);
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('inventory_number')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('serial_number')->nullable()->unique();
            $table->date('acquired_at')->nullable();
            $table->decimal('acquisition_cost', 15, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('department')->nullable();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('condition', 30)->default('Neuf');
            $table->string('availability', 30)->default('Disponible');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            $table->timestamp('assigned_at');
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('registration_number')->unique();
            $table->string('brand');
            $table->string('model');
            $table->string('type')->nullable();
            $table->unsignedInteger('mileage')->default(0);
            $table->string('department')->nullable();
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('Disponible');
            $table->date('insurance_expires_at')->nullable();
            $table->date('inspection_expires_at')->nullable();
            $table->date('next_maintenance_at')->nullable();
            $table->unsignedInteger('next_maintenance_mileage')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_records', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('maintainable');
            $table->string('type');
            $table->string('provider')->nullable();
            $table->date('scheduled_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->decimal('cost', 15, 2)->default(0);
            $table->string('status', 30)->default('Planifiée');
            $table->text('description');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('material_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('beneficiary')->nullable();
            $table->decimal('estimated_amount', 15, 2)->default(0);
            $table->decimal('authorized_amount', 15, 2)->default(0);
            $table->decimal('committed_amount', 15, 2)->default(0);
            $table->decimal('disbursed_amount', 15, 2)->default(0);
            $table->decimal('justified_amount', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('status', 40)->default('Brouillon');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('system_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title');
            $table->text('message');
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group', 80)->default('general');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('system_notifications');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('maintenance_records');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('asset_assignments');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_sessions');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('storage_locations');
        Schema::dropIfExists('departments');

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn(['from_location', 'to_location', 'is_emergency', 'validation_status', 'justification']);
        });
        Schema::table('material_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['category', 'received_at', 'cancellation_reason']);
        });
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn(['maximum_threshold', 'unit_cost', 'supplier']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'deactivated_at']);
        });
    }
};
