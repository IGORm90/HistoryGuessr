<?php

namespace App\Providers;

use App\Services\DistanceCalculator;
use App\Services\ScoreCalculator;
use App\Support\Filesystem\PublicPresigningS3Adapter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScoreCalculator::class, fn (Application $app) => ScoreCalculator::fromConfig($app->make(DistanceCalculator::class)));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        JsonResource::withoutWrapping();

        RateLimiter::for('game', fn (Request $request) => [
            Limit::perMinute(60)->by('session:'.($request->hasSession() ? $request->session()->getId() : $request->ip())),
            Limit::perMinute(300)->by('ip:'.$request->ip()),
        ]);

        $this->configurePresignedUrls();
    }

    /**
     * Use the public-endpoint presigning adapter only when the S3 endpoint differs for the browser (local Docker).
     */
    private function configurePresignedUrls(): void
    {
        Storage::extend('s3', function (Application $app, array $config) {
            $adapter = $app->make('filesystem')->createS3Driver($config);

            if (empty($config['public_endpoint'])) {
                return $adapter;
            }

            return new PublicPresigningS3Adapter(
                $adapter->getDriver(),
                $adapter->getAdapter(),
                $config + ['version' => 'latest'],
                $adapter->getClient(),
            );
        });
    }
}
