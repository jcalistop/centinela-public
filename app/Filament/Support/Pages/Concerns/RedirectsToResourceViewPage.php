<?php

namespace App\Filament\Support\Pages\Concerns;

use Illuminate\Database\Eloquent\Model;

trait RedirectsToResourceViewPage
{
    /**
     * @return class-string
     */
    abstract public static function getResource(): string;

    abstract public function getRecord(): ?Model;

    protected function getRedirectUrl(): string
    {
        return $this->resourceViewOrIndexUrl($this->getRecord());
    }

    protected function resourceViewOrIndexUrl(?Model $record): string
    {
        $resource = static::getResource();

        if ($record !== null && $resource::hasPage('view') && $resource::canView($record)) {
            return $resource::getUrl('view', ['record' => $record]);
        }

        return $resource::getUrl('index');
    }
}
