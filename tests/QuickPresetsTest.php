<?php

use Blemli\FormSettings\FormSettings;
use Blemli\FormSettings\FormSettingsPlugin;
use Blemli\FormSettings\Livewire\SettingsPanel;
use Blemli\FormSettings\Livewire\VisitPresetHook;
use Blemli\FormSettings\Storage\SessionStore;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleComponents\ComponentContext;

class QuickCreatePage extends CreateRecord
{
    protected static string $resource = QuickResource::class;
}

class QuickEditPage extends EditRecord
{
    protected static string $resource = QuickResource::class;
}

/**
 * All quickActions() needs from a resource: its pages and their URLs.
 */
class QuickResource
{
    public static function getPages(): array
    {
        return ['create' => QuickCreatePage::class, 'edit' => QuickEditPage::class];
    }

    public static function getUrl(string $name, array $parameters = []): string
    {
        $record = $parameters['record'] ?? null;
        unset($parameters['record']);

        return '/quick/' . ($record ? "{$record->getKey()}/" : '') . $name . ($parameters ? '?' . http_build_query($parameters) : '');
    }
}

/**
 * A manager with a plugin but without a panel: presets on, and a form of
 * three fields for sanitizing.
 */
function quickManager(?FormSettingsPlugin $plugin = null): FormSettings
{
    $manager = new class($plugin ?? FormSettingsPlugin::make()->presets()) extends FormSettings
    {
        public function __construct(protected ?FormSettingsPlugin $fakePlugin)
        {
            parent::__construct();
        }

        public function plugin(): ?FormSettingsPlugin
        {
            return $this->fakePlugin;
        }

        public function describeFields(object $livewire): array
        {
            return [
                ['name' => 'title', 'label' => 'Title', 'icon' => '', 'required' => true, 'hideable' => false, 'locked' => false, 'group' => null],
                ['name' => 'color', 'label' => 'Color', 'icon' => '', 'required' => false, 'hideable' => true, 'locked' => false, 'group' => 'Look'],
                ['name' => 'notes', 'label' => 'Notes', 'icon' => '', 'required' => false, 'hideable' => true, 'locked' => false, 'group' => null],
            ];
        }

        public function actionOptions(object $livewire): array
        {
            return ['create' => 'Create', 'create_back' => 'Create & back'];
        }
    };

    app()->instance(FormSettings::class, $manager);

    return $manager;
}

function actingUser(): void
{
    test()->actingAs(new class extends User
    {
        protected $guarded = [];
    });
}

it('sanitizes settings against the form', function () {
    $fields = [
        ['name' => 'title', 'hideable' => false, 'group' => null],
        ['name' => 'color', 'hideable' => true, 'group' => 'Look'],
    ];

    expect((new FormSettings)->sanitize([
        'order' => ['ghost', 'color', 'title'],
        'hidden' => ['title', 'color', 'ghost'],
        'entry_point' => 'color',
        'action' => 'explode',
        'start_tab' => 'Nowhere',
        'quick_create' => true,
    ], $fields, ['save' => 'Save']))->toBe([
        'order' => ['color', 'title'],
        'hidden' => ['color'],
        'entry_point' => null,
        'action' => null,
        'start_tab' => null,
    ]);
});

it('keeps the quick flags inside the preset, in both stores', function () {
    $manager = quickManager();
    $key = 'default::' . str_replace('\\', '.', QuickCreatePage::class);

    $manager->store()->putPreset($key, 'Quick entry', ['hidden' => ['color']]);
    $manager->store()->putPreset($key, 'Full', ['hidden' => []]);

    expect($manager->quickPresets($key, 'create'))->toBe([]);

    $manager->setPresetQuick($key, 'Quick entry', 'create');
    $manager->setPresetQuick($key, 'Quick entry', 'explode');
    $manager->setPresetQuick($key, 'Missing', 'create');

    expect($manager->quickPresets($key, 'create'))->toBe(['Quick entry'])
        ->and($manager->quickPresets($key, 'edit'))->toBe([])
        ->and($manager->store()->getPreset($key, 'Quick entry')['hidden'])->toBe(['color'])
        ->and($manager->store()->listPresets($key))->toBe(['Quick entry', 'Full']);

    $manager->setPresetQuick($key, 'Quick entry', 'create', false);

    expect($manager->quickPresets($key, 'create'))->toBe([]);
});

it('never lets the flags leak into the applied settings', function () {
    expect((new FormSettings)->normalize(['hidden' => ['a'], 'quick_create' => true, 'quick_edit' => true]))
        ->not->toHaveKey('quick_create')
        ->not->toHaveKey('quick_edit');
});

it('toggles the quick flags from the panel and keeps them when a preset is overwritten', function () {
    $key = 'panel::quick-form';

    $panel = Livewire::test(SettingsPanel::class, [
        'formKey' => $key,
        'fields' => [
            ['name' => 'title', 'label' => 'Title', 'icon' => 'heroicon-o-pencil', 'required' => true, 'hideable' => false, 'group' => null],
            ['name' => 'color', 'label' => 'Color', 'icon' => 'heroicon-o-swatch', 'required' => false, 'hideable' => true, 'group' => null],
            ['name' => 'notes', 'label' => 'Notes', 'icon' => 'heroicon-o-bars-3', 'required' => false, 'hideable' => true, 'group' => null],
        ],
        'actionOptions' => ['create' => 'Create'],
        'presetsEnabled' => true,
        'quickOperations' => ['create'],
    ]);

    $panel->call('toggleHidden', 'color')
        ->set('newPresetName', 'Quick entry')
        ->call('savePreset')
        ->assertSet('presets', ['Quick entry'])
        ->assertSeeHtml('toggleQuick(')
        ->call('toggleQuick', 'Quick entry', 'create')
        ->assertSet('quickPresets', ['create' => ['Quick entry']])
        // An operation this form does not cover is ignored.
        ->call('toggleQuick', 'Quick entry', 'edit')
        ->assertSet('quickPresets', ['create' => ['Quick entry']])
        ->call('toggleQuick', 'Nope', 'create')
        ->assertSet('quickPresets', ['create' => ['Quick entry']]);

    // Overwrite with another layout: still a quick-create preset.
    $panel->call('toggleHidden', 'notes')
        ->set('newPresetName', 'Quick entry')
        ->call('savePreset')
        ->call('savePreset')
        ->assertSet('quickPresets', ['create' => ['Quick entry']]);

    expect((new SessionStore)->getPreset($key, 'Quick entry')['hidden'])->toBe(['color', 'notes']);

    $panel->call('toggleQuick', 'Quick entry', 'create')
        ->assertSet('quickPresets', ['create' => []]);
});

it('shows no quick toggles on forms that are neither create nor edit pages', function () {
    $key = 'panel::plain-form';
    (new SessionStore)->putPreset($key, 'Mine', ['hidden' => ['color']]);

    Livewire::test(SettingsPanel::class, [
        'formKey' => $key,
        'fields' => [['name' => 'color', 'label' => 'Color', 'icon' => 'heroicon-o-swatch', 'required' => false, 'hideable' => true, 'group' => null]],
        'actionOptions' => [],
        'presetsEnabled' => true,
    ])->assertSee('Mine')->assertDontSeeHtml('toggleQuick(');
});

it('hands out one url action per quick preset of a resource', function () {
    actingUser();
    $manager = quickManager(FormSettingsPlugin::make()->presets()->predefined([
        QuickCreatePage::class => [
            'Built in' => ['hidden' => ['notes'], 'quick_create' => true],
            'Not quick' => ['hidden' => ['notes']],
        ],
    ]));

    $key = $manager->keyFor(QuickCreatePage::class);
    $manager->store()->putPreset($key, 'Quick entry', ['hidden' => ['color'], 'quick_create' => true]);
    $manager->store()->putPreset($key, 'Private', ['hidden' => ['color']]);

    $actions = $manager->quickActions(QuickResource::class, 'create');

    expect($actions)->toHaveCount(2)
        ->and($actions[0])->toBeInstanceOf(Action::class)
        ->and(array_map(fn (Action $action): string => $action->getLabel(), $actions))->toBe(['Built in', 'Quick entry'])
        ->and($actions[0]->getUrl())->toBe('/quick/create?formsettings-preset=Built+in&formsettings-source=predefined')
        ->and($actions[1]->getUrl())->toBe('/quick/create?formsettings-preset=Quick+entry')
        ->and($actions[1]->getName())->toStartWith('formsettings-create-')
        ->and($manager->quickActions(QuickResource::class, 'edit'))->toBe([])
        ->and($manager->quickActions(QuickResource::class, 'explode'))->toBe([])
        ->and($manager->quickActions('Missing\\Resource', 'create'))->toBe([]);
});

it('hides quick edit actions that have no record to edit', function () {
    actingUser();
    $manager = quickManager(FormSettingsPlugin::make()->presets());

    $manager->store()->putPreset($manager->keyFor(QuickEditPage::class), 'Fix typo', ['hidden' => ['color'], 'quick_edit' => true]);

    $actions = $manager->quickActions(QuickResource::class, 'edit');

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->isVisible())->toBeFalse();

    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill(['id' => 7]);

    $actions[0]->record($record);

    expect($actions[0]->isVisible())->toBeTrue()
        ->and($actions[0]->getUrl())->toBe('/quick/7/edit?formsettings-preset=Fix+typo');
});

it('shares quick presets between create and edit with perResource', function () {
    actingUser();
    $manager = quickManager(FormSettingsPlugin::make()->presets()->perResource());

    expect($manager->keyFor(QuickCreatePage::class))->toBe($manager->keyFor(QuickEditPage::class))
        ->toBe('default::QuickResource');

    $manager->store()->putPreset('default::QuickResource', 'Both', ['hidden' => ['color'], 'quick_create' => true, 'quick_edit' => true]);

    expect($manager->quickActions(QuickResource::class, 'create'))->toHaveCount(1)
        ->and($manager->quickActions(QuickResource::class, 'edit'))->toHaveCount(1);
});

it('offers no quick actions without presets, without authorization or on excepted pages', function () {
    $key = 'default::' . str_replace('\\', '.', QuickCreatePage::class);
    (new SessionStore)->putPreset($key, 'Quick entry', ['hidden' => ['color'], 'quick_create' => true]);

    actingUser();

    expect(quickManager()->quickActions(QuickResource::class, 'create'))->toHaveCount(1)
        ->and(quickManager(FormSettingsPlugin::make())->quickActions(QuickResource::class, 'create'))->toBe([])
        ->and(quickManager(FormSettingsPlugin::make()->presets()->authorize(false))->quickActions(QuickResource::class, 'create'))->toBe([])
        ->and(quickManager(FormSettingsPlugin::make()->presets()->except([QuickCreatePage::class]))->quickActions(QuickResource::class, 'create'))->toBe([])
        ->and(quickManager(FormSettingsPlugin::make()->presets()->optIn())->quickActions(QuickResource::class, 'create'))->toBe([]);
});

it('applies a preset for one visit without touching the stored settings', function () {
    actingUser();
    $manager = quickManager();
    $page = new QuickCreatePage;
    $key = $manager->keyFor($page);

    $manager->store()->put($key, ['hidden' => ['notes'], 'action' => 'create_back']);
    $manager->store()->putPreset($key, 'Quick entry', [
        'hidden' => ['color', 'title', 'ghost'],
        'entry_point' => 'notes',
        'quick_create' => true,
    ]);

    expect($manager->settingsFor($page)['hidden'])->toBe(['notes'])
        ->and($manager->visitFor($page))->toBeNull();

    $manager->startVisit($page, 'Quick entry');

    expect($manager->visitFor($page))->toBe(['name' => 'Quick entry', 'source' => 'own'])
        // Sanitized: the required field and the unknown one stay visible.
        ->and($manager->settingsFor($page))->toBe([
            'order' => [],
            'hidden' => ['color'],
            'entry_point' => 'notes',
            'action' => null,
            'start_tab' => null,
        ])
        ->and($manager->selectedAction($page))->toBe('create')
        // Another instance of the same page is not on a visit.
        ->and($manager->settingsFor(new QuickCreatePage)['hidden'])->toBe(['notes'])
        ->and($manager->store()->get($key)['hidden'])->toBe(['notes']);

    $manager->endVisit($page);

    expect($manager->settingsFor($page)['hidden'])->toBe(['notes'])
        ->and($manager->selectedAction($page))->toBe('create_back');
});

it('visits a predefined preset and quietly drops a preset that is gone', function () {
    actingUser();
    $manager = quickManager(FormSettingsPlugin::make()->presets()->predefined([
        QuickCreatePage::class => ['Built in' => ['hidden' => ['notes']]],
    ]));
    $page = new QuickCreatePage;

    $manager->startVisit($page, 'Built in', 'predefined');

    expect($manager->settingsFor($page)['hidden'])->toBe(['notes']);

    $manager->startVisit($page, 'Vanished');

    expect($manager->settingsFor($page)['hidden'])->toBe([])
        ->and($manager->visitFor($page))->toBeNull();
});

it('carries the visit in the snapshot memo and ends it on the gear refresh', function () {
    actingUser();
    $manager = quickManager();
    $page = new QuickCreatePage;
    $manager->store()->putPreset($manager->keyFor($page), 'Quick entry', ['hidden' => ['color']]);

    $hook = new VisitPresetHook;
    $hook->setComponent($page);

    // The preset is named in the URL once …
    request()->query->set(FormSettings::PRESET_PARAM, 'Quick entry');
    $hook->mount([], null);
    request()->query->remove(FormSettings::PRESET_PARAM);

    expect($manager->visitFor($page))->toBe(['name' => 'Quick entry', 'source' => 'own']);

    // … then travels in the memo …
    $context = new ComponentContext($page);
    $hook->dehydrate($context);

    expect($context->memo[VisitPresetHook::MEMO])->toBe(['name' => 'Quick entry', 'source' => 'own']);

    // … and comes back on the next request, for a fresh instance.
    $next = new QuickCreatePage;
    $nextHook = new VisitPresetHook;
    $nextHook->setComponent($next);
    $nextHook->hydrate($context->memo);

    expect($manager->settingsFor($next)['hidden'])->toBe(['color']);

    // Other calls pass through; the gear's refresh ends the visit.
    $returned = false;
    $nextHook->call('save', [], function () use (&$returned) {
        $returned = true;
    });

    expect($returned)->toBeFalse()->and($manager->visitFor($next))->not->toBeNull();

    $nextHook->call(VisitPresetHook::REFRESH, [], function () use (&$returned) {
        $returned = true;
    });

    expect($returned)->toBeTrue()->and($manager->visitFor($next))->toBeNull();

    $after = new ComponentContext($next);
    $nextHook->dehydrate($after);

    expect($after->memo)->not->toHaveKey(VisitPresetHook::MEMO);
});

it('shows the visit in the panel, keeps it on request, or leaves it', function () {
    $key = 'panel::visit-form';
    (new SessionStore)->put($key, ['hidden' => ['notes']]);

    $make = fn () => Livewire::test(SettingsPanel::class, [
        'formKey' => $key,
        'fields' => [
            ['name' => 'color', 'label' => 'Color', 'icon' => 'heroicon-o-swatch', 'required' => false, 'hideable' => true, 'group' => null],
            ['name' => 'notes', 'label' => 'Notes', 'icon' => 'heroicon-o-bars-3', 'required' => false, 'hideable' => true, 'group' => null],
        ],
        'actionOptions' => ['create' => 'Create'],
        'presetsEnabled' => true,
        'visitPreset' => 'Quick entry',
        'visitSettings' => ['order' => [], 'hidden' => ['color'], 'entry_point' => null, 'action' => null, 'start_tab' => null],
    ]);

    // The panel shows what the page is using, and has stored nothing.
    $make()
        ->assertSet('hidden', ['color'])
        ->assertSee('Quick entry')
        ->assertSee('this visit only');

    expect((new SessionStore)->get($key)['hidden'])->toBe(['notes']);

    $make()->call('leaveVisit')
        ->assertSet('hidden', ['notes'])
        ->assertSet('visitPreset', null)
        ->assertDispatched('formsettings-updated')
        ->assertDontSee('this visit only');

    expect((new SessionStore)->get($key)['hidden'])->toBe(['notes']);

    $make()->call('keepVisit')
        ->assertSet('visitPreset', null)
        ->assertDispatched('formsettings-updated');

    expect((new SessionStore)->get($key)['hidden'])->toBe(['color']);
});
