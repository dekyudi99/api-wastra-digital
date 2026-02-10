<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\OrderItem;
use App\Models\CancelRequest;
use App\Models\Withdrawal;
use App\Models\Review;
use App\Policies\OrderItemPolicy;
use App\Policies\CancelRequestPolicy;
use App\Policies\WithdrawalPolicy;
use App\Policies\ReviewPolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        OrderItem::class => OrderItemPolicy::class,
        CancelRequest::class => CancelRequestPolicy::class,
        Withdrawal::class => WithdrawalPolicy::class,
        Review::class => ReviewPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
