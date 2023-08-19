<?php

namespace Database\Seeders;

use App\Models\Configuration;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if((new Configuration)->whereName('GOOGLE_CLIENT_ID')->count() != 0) return;
        $google_client_id = new Configuration();
        $google_client_id->name = "GOOGLE_CLIENT_ID";
        $google_client_id->sValue = "";
        $google_client_id->type = "s";
        $google_client_id->save();
        if((new Configuration)->whereName('GOOGLE_CLIENT_SECRET')->count() != 0) return;
        $google_client_secret = new Configuration();
        $google_client_secret->name = "GOOGLE_CLIENT_SECRET";
        $google_client_secret->sValue = "";
        $google_client_secret->type = "s";
        $google_client_secret->save();
        if((new Configuration)->whereName('GOOGLE_REDIRECT_URI')->count() != 0) return;
        $google_redirect_uri = new Configuration();
        $google_redirect_uri->name = "GOOGLE_REDIRECT_URI";
        $google_redirect_uri->sValue = env("APP_URL")."/auth/google/callback";
        $google_redirect_uri->type = "s";
        $google_redirect_uri->save();

    }
}
