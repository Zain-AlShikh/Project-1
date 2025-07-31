<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterRatingColumnInBookUserRatingsTable extends Migration
{
    public function up()
    {
        Schema::table('book_user_ratings', function (Blueprint $table) {
            $table->decimal('rating', 3, 1)->unsigned()->change(); // تعديل العمود
        });
    }

    public function down()
    {
        Schema::table('book_user_ratings', function (Blueprint $table) {
            $table->tinyInteger('rating')->unsigned()->change(); // الرجوع للقديم إذا لزم
        });
    }
}