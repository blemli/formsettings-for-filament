<?php

namespace Blemli\FormSettings\Livewire;

use Blemli\FormSettings\FormSettings;
use Livewire\ComponentHook;
use Throwable;

/**
 * Opens a page with one preset for this visit only:
 * /shoots/create?formsettings-preset=Quick%20entry
 *
 * The preset is named in the URL once and then travels in the component's
 * snapshot memo — tamper-proof, tied to this page instance, gone with it.
 * Nothing is written to the user's saved settings.
 */
class VisitPresetHook extends ComponentHook
{
    public const MEMO = 'formsettingsVisit';

    /**
     * The gear refreshes the page through this pseudo-method after every
     * change in the panel; a change ends the visit (the panel has just
     * saved what the user now sees, or went back to their own settings).
     */
    public const REFRESH = 'formsettingsRefresh';

    /**
     * @param  array<mixed>  $params
     */
    public function mount($params, $parent): void
    {
        try {
            $name = request()->query(FormSettings::PRESET_PARAM);

            if (! is_string($name) || $name === '') {
                return;
            }

            $manager = app(FormSettings::class);

            if (! $manager->isEnabledFor($this->component)) {
                return;
            }

            $source = request()->query(FormSettings::SOURCE_PARAM);

            $manager->startVisit($this->component, $name, is_string($source) ? $source : 'own');
        } catch (Throwable) {
        }
    }

    /**
     * @param  array<string, mixed>  $memo
     */
    public function hydrate($memo): void
    {
        $visit = $memo[self::MEMO] ?? null;

        if (is_array($visit) && is_string($visit['name'] ?? null)) {
            app(FormSettings::class)->startVisit($this->component, $visit['name'], (string) ($visit['source'] ?? 'own'));
        }
    }

    public function dehydrate($context): void
    {
        $visit = app(FormSettings::class)->visitFor($this->component);

        if ($visit !== null) {
            $context->addMemo(self::MEMO, $visit);
        }
    }

    /**
     * @param  array<mixed>  $params
     */
    public function call($method, $params, $returnEarly): void
    {
        if ($method !== self::REFRESH) {
            return;
        }

        app(FormSettings::class)->endVisit($this->component);

        // Not a real method: answering here is the whole call. The
        // component still re-renders, which is the refresh.
        $returnEarly(null);
    }
}
