<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;

/**
 * For a relation manager that borrows another resource's table and form: its create action
 * opens that form in a modal, so the new record belongs to this page's record. By default it
 * links to the other resource's create page, which doesn't know whose page you came from.
 *
 * @phpstan-require-extends RelationManager
 */
trait CreatesRelatedInModal
{
    public function getDefaultActionUrl(Action $action): ?string
    {
        return $action instanceof CreateAction ? null : parent::getDefaultActionUrl($action);
    }
}
