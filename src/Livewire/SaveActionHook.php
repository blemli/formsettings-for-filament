<?php

namespace Blemli\FormSettings\Livewire;

use Blemli\FormSettings\FormSettings;
use Blemli\FormSettings\Support\RecordNavigator;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Livewire\ComponentHook;

class SaveActionHook extends ComponentHook
{
    /**
     * Reroute the page's submit method to the save action the user
     * selected in the formsettings panel.
     */
    public function call(string $method, array $params, callable $returnEarly): void
    {
        $component = $this->component;

        if ($component instanceof EditRecord && $method === 'save') {
            $this->handleSave($component, $returnEarly);
        }

        if ($component instanceof CreateRecord && $method === 'create') {
            $this->handleCreate($component, $returnEarly);
        }
    }

    protected function handleSave(EditRecord $page, callable $returnEarly): void
    {
        $action = $this->selectedAction($page);

        if ($action === 'save_next') {
            $page->save(shouldRedirect: false);
            $this->redirect($page, RecordNavigator::nextUrl($page) ?? RecordNavigator::indexUrl($page));
            $returnEarly(null);
        }

        if ($action === 'save_back') {
            $page->save(shouldRedirect: false);
            $this->redirect($page, RecordNavigator::indexUrl($page));
            $returnEarly(null);
        }
    }

    protected function handleCreate(CreateRecord $page, callable $returnEarly): void
    {
        $action = $this->selectedAction($page);

        if ($action === 'create_next') {
            $page->create(another: true);
            $returnEarly(null);
        }

        if ($action === 'create_back') {
            $page->create();
            $this->redirect($page, RecordNavigator::indexUrl($page));
            $returnEarly(null);
        }
    }

    protected function selectedAction(object $page): ?string
    {
        $manager = app(FormSettings::class);

        if (! $manager->isEnabledFor($page)) {
            return null;
        }

        $action = $manager->selectedAction($page);

        return $action === $manager->defaultAction($page) ? null : $action;
    }

    protected function redirect(object $page, ?string $url): void
    {
        if ($url !== null) {
            $page->redirect($url);
        }
    }
}
