<?php

use Blemli\FormSettings\Livewire\SaveActionHook;
use Filament\Support\Facades\FilamentView;

/*
 * «Save & back» / «save & next» must redirect the way Filament's own save()
 * does: as a Livewire navigation when the panel runs in SPA mode. A full page
 * load discards the document — and with it the user activation browsers
 * need before they let a sound (Safari, Firefox) or anything else start.
 */
function redirectRecorder(): object
{
    return new class
    {
        public ?string $url = null;

        public ?bool $navigate = null;

        public function redirect(string $url, bool $navigate = false): void
        {
            $this->url = $url;
            $this->navigate = $navigate;
        }
    };
}

it('redirects with a livewire navigation in an spa panel', function () {
    FilamentView::spa(true);

    $page = redirectRecorder();
    (new SaveActionHook)->redirect($page, 'http://localhost/admin/posts');

    expect($page->url)->toBe('http://localhost/admin/posts')
        ->and($page->navigate)->toBeTrue();
});

it('falls back to a full redirect without spa mode and skips a missing url', function () {
    FilamentView::spa(false);

    $page = redirectRecorder();
    (new SaveActionHook)->redirect($page, 'http://localhost/admin/posts');

    expect($page->navigate)->toBeFalse();

    $untouched = redirectRecorder();
    (new SaveActionHook)->redirect($untouched, null);

    expect($untouched->url)->toBeNull();
});
