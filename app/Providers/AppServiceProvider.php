<?php

declare(strict_types = 1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;
use Override;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->configDate();
        $this->configCommands();
        $this->configModels();
        $this->setupLogViewer();
        $this->configUrls();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Configures Eloquent models by disabling the requirement for defining
     * the fillable property and enforcing strict checking to ensure that
     * all accessed properties exist within the model.
     */
    private function configModels(): void
    {
        // --
        // Make sure that all properties being called exist in the model
        Model::shouldBeStrict();
    }

    /**
     * Configures the application to use CarbonImmutable for date and time handling.
     */
    private function configDate(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Configures database commands to prohibit execution of destructive statements
     * when the application is running in a production environment.
     */
    private function configCommands(): void
    {
        DB::prohibitDestructiveCommands(
            app()->isProduction()
        );
    }

    private function setupLogViewer(): void
    {
        LogViewer::auth(function ($request): void {
            //            todo: Ao finalizar a etapa de autentiação e níveis de acesso, finalizar este método corretamente.
            //            return $request->user()->isAdmin();
        });
    }

    private function configUrls(): void
    {
        URL::forceHttps(
            app()->isProduction()
        );
    }
}
