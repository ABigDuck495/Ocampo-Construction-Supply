<?php

namespace App\Providers;

use App\Models\OrderItem;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::$snakeAttributes = false;

        View::composer('partials.sidebar', function ($view) {
            $view->with('sidebarDeliveryBadge', OrderItem::awaitingDispatchOrderCount());
        });
    }
}
