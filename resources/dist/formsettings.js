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

    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morphed', () => queueMicrotask(applyLabel))
    })

    document.addEventListener('DOMContentLoaded', applyLabel)
    queueMicrotask(applyLabel)

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
