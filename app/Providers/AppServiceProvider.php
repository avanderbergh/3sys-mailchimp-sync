<?php

namespace App\Providers;

use App\Services\WCBSApi;
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
    }
}
