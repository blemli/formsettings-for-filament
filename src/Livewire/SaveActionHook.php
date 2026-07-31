<?php

namespace Blemli\FormSettings\Livewire;

use Blemli\FormSettings\FormSettings;
use Blemli\FormSettings\Support\RecordNavigator;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Livewire\ComponentHook;

use function Livewire\wrap;

class SaveActionHook extends ComponentHook
{
    /**
     * Reroute the page's submit method to the save action the user
     * selected in the formsettings panel.
     *
     * The page method is invoked through wrap() so a ValidationException
     * is converted into component errors exactly like a native call.
     * After a wrapped call the error bag decides whether the submit
     * succeeded — only then is the custom redirect applied.
     */
    public function call(string $method, array $params, callable $returnEarly): void
    {
        $component = $this->component;

        // The visible "save & back" button submits with a marker param;
        // it is handled here and never reaches the real page method.
        $isBackSubmit = in_array('formsettings-back', $params, true);

        if ($component instanceof EditRecord && $method === 'save') {
            $isBackSubmit
                ? $this->handleBackSubmit($component, $returnEarly, fn () => wrap($component)->save(false))
                : $this->handleSave($component, $returnEarly);
        }

        if ($component instanceof CreateRecord && $method === 'create') {
            $isBackSubmit
                ? $this->handleBackSubmit($component, $returnEarly, fn () => wrap($component)->create())
                : $this->handleCreate($component, $returnEarly);
        }
    }

    protected function handleBackSubmit(CreateRecord | EditRecord $page, callable $returnEarly, callable $submit): void
    {
        $manager = app(FormSettings::class);

        if (! $manager->isEnabledFor($page) || ! ($manager->plugin()?->hasSaveAndBackButton() ?? false)) {
            return;
        }

        $submit();

        if ($page->getErrorBag()->isEmpty()) {
            $this->redirect($page, RecordNavigator::indexUrl($page));
        }

        $returnEarly(null);
    }

    protected function handleSave(EditRecord $page, callable $returnEarly): void
    {
        $action = $this->selectedAction($page);

        if ($action === null) {
            return;
        }

        wrap($page)->save(false);

        if ($page->getErrorBag()->isEmpty()) {
            $this->redirect($page, match ($action) {
                'save_next' => RecordNavigator::nextUrl($page) ?? RecordNavigator::indexUrl($page),
                'save_back' => RecordNavigator::indexUrl($page),
                default => null,
            });
        }

        $returnEarly(null);
    }

    protected function handleCreate(CreateRecord $page, callable $returnEarly): void
    {
        $action = $this->selectedAction($page);

        if ($action === null) {
            return;
        }

        if ($action === 'create_next') {
            wrap($page)->create(true);
            $returnEarly(null);

            return;
        }

        wrap($page)->create();

        if ($page->getErrorBag()->isEmpty()) {
            $this->redirect($page, RecordNavigator::indexUrl($page));
        }

        $returnEarly(null);
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
