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

    const focusEntryPoint = () => {
        const marked = document.querySelector('[data-formsettings-entry]')

        if (!marked) {
            return
        }

        const target = marked.matches('input, select, textarea, button')
            ? marked
            : marked.querySelector('input, select, textarea, button, [tabindex]')

        if (!target) {
            return
        }

        target.focus({ preventScroll: false })
        target.select?.()
    }

    const onPageReady = () => {
        applyLabel()
        setTimeout(focusEntryPoint, 50)
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
