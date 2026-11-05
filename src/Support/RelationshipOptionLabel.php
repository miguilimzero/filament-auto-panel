<?php

namespace Miguilim\FilamentAutoPanel\Support;

use BackedEnum;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;
use Miguilim\FilamentAutoPanel\Filament\Columns\UserColumn;
use Stringable;

class RelationshipOptionLabel
{
    public static function format(Model $record, string $titleColumn, ?UserColumn $userColumn = null): string
    {
        $key = static::text(data_get($record, $record->getKeyName()));
        $title = $titleColumn === $record->getKeyName() ? null : static::text(data_get($record, $titleColumn));
        $label = filled($key) ? '<b>#' . e($key) . '</b>' : '';

        if (filled($title)) {
            $label .= ($label === '' ? '' : ' - ') . e($title);
        }

        if ($userColumn === null || ! $record instanceof Authenticatable) {
            return $label;
        }

        $emailCast = explode(':', $record->getCasts()['email'] ?? '', 2)[0];

        if (! in_array('email', $record->getHidden(), true)
            && (! $record->getVisible() || in_array('email', $record->getVisible(), true))
            && ! in_array($emailCast, ['encrypted', 'hashed'], true)
            && ! is_a($emailCast, AsEncryptedArrayObject::class, true)
            && ! is_a($emailCast, AsEncryptedCollection::class, true)
            && filled($email = static::text(data_get($record, 'email')))) {
            // Filament's gray Schema Text styling includes both light and dark colors.
            $label = '<span style="display:block">' . $label . '</span>'
                . ' <span class="fi-sc-text" style="display:block">' . e($email) . '</span>';
        }

        $avatar = $userColumn->getAvatarHtmlForUser($record);

        return $avatar === null
            ? $label
            : '<span style="display:grid;grid-template-columns:auto minmax(0,1fr);align-items:center">'
                . $avatar->toHtml() . ' <span style="min-width:0">' . $label . '</span></span>';
    }

    protected static function text(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return is_scalar($value) || $value instanceof Stringable ? (string) $value : null;
    }
}
