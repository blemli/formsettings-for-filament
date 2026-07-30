(() => {
    const primaryButton = () =>
        document.querySelector('[data-formsettings-primary]')

    const applyLabel = () => {
        const source = document.querySelector(
            '[data-formsettings-selected-label]',
        )
        const button = primaryButton()

        if (!source || !button) {
            return
        }

        const label = source.getAttribute('data-formsettings-selected-label')

        if (!label) {
            return
        }

        const target = button.querySelector('.fi-btn-label') ?? button

        if (target.textContent.trim() !== label) {
            target.textContent = label
        }
    }

    let userInteracted = false

    const markInteraction = () => {
        userInteracted = true
    }

    window.addEventListener('pointerdown', markInteraction, { capture: true })
    window.addEventListener('keydown', markInteraction, { capture: true })

    const focusEntryPoint = () => {
        const marked = document.querySelector('[data-formsettings-entry]')

        if (!marked) {
            return
        }

        const target = marked.matches('input, select, textarea, button')
            ? marked
            : marked.querySelector('input, select, textarea, button, [tabindex]')

        if (!target || document.activeElement === target) {
            return
        }

        target.focus({ preventScroll: false })
        target.select?.()
    }

    // Filament (and other plugins) may focus their own elements shortly
    // after load, so the entry point re-claims focus in stages until the
    // user interacts.
    const claimFocus = () => {
        for (const delay of [0, 150, 400, 800]) {
            setTimeout(() => {
                if (!userInteracted) {
                    focusEntryPoint()
                }
            }, delay)
        }
    }

    const onPageReady = () => {
        userInteracted = false
        applyLabel()
        claimFocus()
    }

    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morphed', () => queueMicrotask(applyLabel))
    })

    document.addEventListener('DOMContentLoaded', onPageReady)
    document.addEventListener('livewire:navigated', onPageReady)
    window.addEventListener('formsettings-entry-changed', () =>
        setTimeout(focusEntryPoint, 300),
    )

    if (document.readyState !== 'loading') {
        onPageReady()
    }

    window.addEventListener('keydown', (event) => {
        if (!(event.metaKey || event.ctrlKey) || event.key !== 'Enter') {
            return
        }

        const button = primaryButton()

        if (!button) {
            return
        }

        event.preventDefault()
        button.click()
    })
})()
