<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('instancia_usuarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auth_id');
            $table->unsignedBigInteger('instancia_id');
            $table->unsignedBigInteger('usuario_id');
            $table->string('login');
            $table->decimal('saldo', 10);

            $table->foreign('instancia_id')
                ->references('id')
                ->on('instancias')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instance_users');
    }
};
