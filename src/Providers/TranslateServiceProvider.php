<?php


namespace Develona\Translate\Providers;

use Develona\Translate\Translate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

class TranslateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('translate', function ($app) {
            \Log::info('register translate');
            return new Translate($app->config['translate']['default_language'], $app->config['translate']['texts_db']);
        });
    }


    public function boot(): void
    {
        \Log::info('boot translate');
        $this->publishes([
            __DIR__.'/../../config/translate.php' => config_path('translate.php'),
        ]);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'translate');

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'translate');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/translate'),
        ]);

        View::share('editable_texts', session('editable_texts', false));

        Blade::directive('t', function ($expression) {
            return "<?php echo T::html($expression) ?>";
        });
    }
}