<?php

use Illuminate\Database\Seeder;

class StateTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('state_types')->insert([
            ['name' => "Start"],
            ['name' => "End"],
            ['name' => "Waiting"],
            ['name' => "Approved"],
            ['name' => "Rejected"],
            ['name' => "Cancelled"]
        ]);
    }
}
