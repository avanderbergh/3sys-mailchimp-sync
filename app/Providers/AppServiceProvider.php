<?php

namespace App\Providers;

use App\Services\WCBSApi;
use App\Services\WCBSApi2;
use Illuminate\Support\Facades\App;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        App::singleton('App\Services\WCBSApi', function () {
            $login = env('WCBS_API_LOGIN');
            $password = env('WCBS_API_PASSWORD');

            return new WCBSApi($login, $password);
        });
        App::singleton('App\Services\WCBSApi2', function() {
           $client_id = env('WCBS_CLIENT_ID');
           $client_secret = env('WCBS_CLIENT_SECRET');

           return new WCBSApi2($client_id, $client_secret);
        });
    }
}
