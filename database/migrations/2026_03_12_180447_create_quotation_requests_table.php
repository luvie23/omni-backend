<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_requests', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('state', 2);
            $table->string('zip', 10);

            $table->string('phone_number');
            $table->string('email');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_requests');
    }
};
