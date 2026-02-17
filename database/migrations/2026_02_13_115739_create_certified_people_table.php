<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certified_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')
                ->constrained('contractors')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('certification_number')->nullable();
            $table->timestamps();
            $table->unique(['contractor_id', 'certification_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certified_people');
    }
};
