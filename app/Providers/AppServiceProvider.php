<?php

namespace App\Providers;

use App\Enums\CompletableType;
use App\Services\Jobs\SkillMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            SkillMatcher::class,
            fn () => new SkillMatcher(database_path('data/skills.json')),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
    }

    /**
     * Store short aliases instead of class names in `completions.completable_type`.
     */
    protected function configureMorphMap(): void
    {
        Relation::morphMap(
            collect(CompletableType::cases())
                ->mapWithKeys(fn (CompletableType $type) => [$type->value => $type->model()])
                ->all(),
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
