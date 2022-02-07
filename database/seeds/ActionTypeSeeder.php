<?php

use Illuminate\Database\Seeder;

class ActionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('action_types')->insert([
            ['name' => "approve"],
            ['name' => "reject"],
            ['name' => "cancel"]
        ]);
    }
}
