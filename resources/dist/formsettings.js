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
    let guardUntil = 0

    const markInteraction = () => {
        userInteracted = true
    }

    window.addEventListener('pointerdown', markInteraction, { capture: true })
    window.addEventListener('keydown', markInteraction, { capture: true })

    const entryTarget = () => {
        const marked = document.querySelector('[data-formsettings-entry]')

        if (!marked) {
            return null
        }

        return marked.matches('input, select, textarea, button')
            ? marked
            : marked.querySelector('input, select, textarea, button, [tabindex]')
    }

    const focusEntryPoint = () => {
        const target = entryTarget()

        if (!target || document.activeElement === target) {
            return
        }

        target.focus({ preventScroll: false })
        target.select?.()
    }

    // Other scripts (Filament, plugins, native autofocus) may focus their
    // own element at any point after load — the timing differs per
    // browser. Instead of guessing delays, watch focus changes for a
    // short window and take focus back whenever it was moved
    // programmatically. Real user interaction ends the guard instantly.
    window.addEventListener(
        'focusin',
        (event) => {
            if (userInteracted || performance.now() > guardUntil) {
                return
            }

            const target = entryTarget()

            if (!target || event.target === target) {
                return
            }

            requestAnimationFrame(() => {
                if (!userInteracted && performance.now() <= guardUntil) {
                    focusEntryPoint()
                }
            })
        },
        { capture: true },
    )

    const onPageReady = () => {
        userInteracted = false
        guardUntil = performance.now() + 2500
        applyLabel()
        focusEntryPoint()
        setTimeout(focusEntryPoint, 150)
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
