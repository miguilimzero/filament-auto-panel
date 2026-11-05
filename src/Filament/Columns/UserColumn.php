<?php

namespace Miguilim\FilamentAutoPanel\Filament\Columns;

use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class UserColumn extends TextColumn
{
    protected string $avatarAttribute = 'profile_photo_url';

    protected ?HtmlSanitizer $avatarSanitizer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prefix(fn (?Model $record): ?HtmlString => $this->getAvatarHtml($record));
    }

    public function avatarAttribute(string $attribute): static
    {
        $this->avatarAttribute = $attribute;

        return $this;
    }

    public function getAvatarAttribute(): string
    {
        return $this->avatarAttribute;
    }

    protected function getAvatarHtml(?Model $record): ?HtmlString
    {
        if ($record === null || blank($relationship = $this->getRelationshipName($record))) {
            return null;
        }

        $user = data_get($record, $relationship);

        return $this->getAvatarHtmlForUser($user instanceof Model ? $user : null);
    }

    public function getAvatarHtmlForUser(?Model $user): ?HtmlString
    {
        $url = data_get($user, $this->getAvatarAttribute());

        if (! is_string($url) || blank($url)) {
            return null;
        }

        $this->avatarSanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowElement('img', ['src', 'alt', 'class', 'style', 'loading'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeMedias(),
        );

        $html = $this->avatarSanitizer->sanitize(
            '<img src="' . e($url) . '" alt="" loading="lazy"'
            . ' class="fi-avatar fi-circular fi-size-sm"'
            . ' style="display:inline-block;width:1.5rem;height:1.5rem;vertical-align:middle;margin-inline-end:0.5rem">',
        );

        return str_contains($html, ' src=') ? new HtmlString($html) : null;
    }
}
