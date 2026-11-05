<?php

namespace Miguilim\FilamentAutoPanel\Support;

use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;
use Miguilim\FilamentAutoPanel\Models\DatabaseSession;
use Throwable;

class UserSessions
{
    public const METADATA_COLUMNS = ['id', 'user_id', 'ip_address', 'user_agent', 'last_activity'];

    public static function supportsModel(string $modelClass): bool
    {
        try {
            if (config('session.driver') !== 'database'
                || ! is_subclass_of($modelClass, Model::class)
                || ! is_subclass_of($modelClass, Authenticatable::class)) {
                return false;
            }

            static::newModel();

            $panel = Filament::getCurrentPanel();
            $guardName = $panel ? $panel->getAuthGuard() : config('auth.defaults.guard');
            $guards = config('auth.guards');

            if (! is_string($guardName) || $guardName === '' || ! is_array($guards)
                || static::guardModel($guards[$guardName] ?? null) !== $modelClass) {
                return false;
            }

            // A database session row has no guard/provider namespace for its user_id.
            foreach ($guards as $guard) {
                if (! is_array($guard) || ! is_string($guard['driver'] ?? null)) {
                    return false;
                }

                if ($guard['driver'] === 'session' && static::guardModel($guard) !== $modelClass) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public static function newModel(): DatabaseSession
    {
        $connection = config('session.connection') ?? config('database.default');
        $table = config('session.table') ?? 'sessions';

        if (! is_string($connection) || $connection === ''
            || ! is_array(config('database.connections')[$connection] ?? null)
            || ! is_string($table) || $table === '') {
            throw new InvalidArgumentException('The database session connection and table must be configured.');
        }

        return (new DatabaseSession())->setConnection($connection)->setTable($table);
    }

    public static function queryFor(Model $owner): Builder
    {
        $query = static::newModel()->newQuery()->select(static::METADATA_COLUMNS);

        if (! static::supportsModel($owner::class) || ! $owner instanceof Authenticatable || ! $owner->exists) {
            return $query->whereRaw('1 = 0');
        }

        $identifier = $owner->getAuthIdentifier();

        if ((! is_string($identifier) && ! is_int($identifier)) || (string) $identifier === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereNotNull('user_id')
            ->where('user_id', $identifier);
    }

    public static function recordKey(DatabaseSession $session, Model $owner): string
    {
        $key = config('app.key');

        if (is_string($key) && str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }

        if (! is_string($key) || $key === '') {
            throw new LogicException('An application key is required to mask database session identifiers.');
        }

        if (! $owner instanceof Authenticatable || $session->getKey() === null) {
            throw new LogicException('A session and an authenticatable owner are required.');
        }

        $connection = $session->getConnection();
        $context = json_encode([
            'store' => [
                $session->getConnectionName() ?? config('database.default'),
                $connection->getDatabaseName(),
                $connection->getTablePrefix(),
                $session->getTable(),
            ],
            'owner' => [
                $owner::class,
                $owner->getConnectionName() ?? config('database.default'),
                $owner->getTable(),
                (string) $owner->getAuthIdentifier(),
            ],
            'id' => (string) $session->getKey(),
        ], JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', "filament-auto-panel:user-sessions:record:v1\0" . $context, $key);
    }

    protected static function guardModel(mixed $guard): ?string
    {
        if (! is_array($guard) || ($guard['driver'] ?? null) !== 'session'
            || ! is_string($guard['provider'] ?? null) || $guard['provider'] === '') {
            return null;
        }

        $providers = config('auth.providers');
        $provider = is_array($providers) ? ($providers[$guard['provider']] ?? null) : null;
        $model = is_array($provider) ? ($provider['model'] ?? null) : null;

        return is_array($provider) && ($provider['driver'] ?? null) === 'eloquent'
            && is_string($model) && is_subclass_of($model, Model::class)
            && is_subclass_of($model, Authenticatable::class)
                ? $model
                : null;
    }

}
