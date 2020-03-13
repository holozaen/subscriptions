<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use OnlineVerkaufen\Subscriptions\Models\Feature;

class Plan extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('plans', static function (Blueprint $table) {
            $table->increments('id');

            $table->string('state');

            $table->string('type');

            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedInteger('price');
            $table->string('currency')->default('CHF');

            $table->integer('duration')->nullable();
            $table->unsignedInteger('position')->nullable()->default(0);
            $table->timestamps();
        });

        Schema::create('plan_features', static function (Blueprint $table) {
            $table->increments('id');
            $table->integer('plan_id');
            $table->string('code');
            $table->enum('type', [Feature::TYPE_FEATURE, Feature::TYPE_LIMIT])->default('feature');
            $table->unsignedInteger('limit')->default(0);
            $table->string('restricted_model')->nullable();
            $table->string('restricted_relation')->nullable();
            $table->unsignedInteger('position')->default(0);

            $table->string('name');
            $table->text('description')->nullable();


            $table->timestamps();
        });

        Schema::create('plan_subscriptions', static function (Blueprint $table) {
            $table->increments('id');
            $table->integer('plan_id');

            $table->unsignedBigInteger('model_id');
            $table->string('model_type');

            $table->unsignedInteger('price');
            $table->string('currency');

            $table->boolean('is_recurring')->default(true);

            $table->timestamp('test_ends_at')->nullable()->default(Carbon::now());
            $table->timestamp('payment_tolerance_ends_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plan_subscriptions');
    }
}
