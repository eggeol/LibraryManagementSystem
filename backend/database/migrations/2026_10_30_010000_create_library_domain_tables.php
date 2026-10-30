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
            $table->string('role', 24)->default('member')->index();
            $table->string('membership_code', 32)->nullable()->unique();
            $table->string('phone', 32)->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('authors', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->text('biography')->nullable();
            $table->timestamps();
        });

        Schema::create('publishers', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->string('isbn', 20)->nullable()->unique();
            $table->string('title')->index();
            $table->string('subtitle')->nullable();
            $table->string('edition')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('published_year')->nullable();
            $table->string('language', 8)->default('en');
            $table->string('cover_url')->nullable();
            $table->foreignId('publisher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('author_book', function (Blueprint $table): void {
            $table->foreignId('author_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->primary(['author_id', 'book_id']);
        });

        Schema::create('book_category', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'book_id']);
        });

        Schema::create('book_copies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->string('accession_number', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('status', 24)->default('available')->index();
            $table->string('shelf_location', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['book_id', 'status']);
        });

        Schema::create('borrowings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_copy_id')->constrained('book_copies')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('borrowed_at')->index();
            $table->timestamp('due_at')->index();
            $table->timestamp('returned_at')->nullable();
            $table->string('status', 24)->default('borrowed')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_copy_id')->nullable()->constrained('book_copies')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('reserved_at')->index();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 24)->default('requested')->index();
            $table->timestamps();
            $table->index(['book_id', 'status', 'reserved_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('fines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('borrowing_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('status', 24)->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fine_id')->constrained()->cascadeOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 24)->default('cash');
            $table->string('reference')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
        });

        Schema::create('library_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('entity_type', 80)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        DB::statement("CREATE UNIQUE INDEX borrowings_one_active_copy ON borrowings (book_copy_id) WHERE status = 'borrowed'");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        DB::statement('DROP INDEX IF EXISTS borrowings_one_active_copy');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('library_settings');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('fines');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('borrowings');
        Schema::dropIfExists('book_copies');
        Schema::dropIfExists('book_category');
        Schema::dropIfExists('author_book');
        Schema::dropIfExists('books');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('publishers');
        Schema::dropIfExists('authors');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['membership_code']);
            $table->dropIndex(['role']);
            $table->dropIndex(['status']);
            $table->dropColumn(['role', 'membership_code', 'phone', 'status']);
        });
    }
};
