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
        Schema::create('report_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('report_submissions')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('report_questions')->onDelete('cascade');
            $table->json('answer_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_answers');
    }
};
