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

    // The entry point may live inside an inactive Filament tab. Tab
    // panels activate themselves on an `expand` event (the same hook
    // Filament uses to reveal validation errors), so fire it on every
    // concealing ancestor before trying to focus.
    const revealEntryPoint = (target) => {
        let revealed = false
        let panel = target.closest('.fi-sc-tabs-tab:not(.fi-active)')

        while (panel) {
            panel.dispatchEvent(new CustomEvent('expand'))
            revealed = true
            panel =
                panel.parentElement?.closest(
                    '.fi-sc-tabs-tab:not(.fi-active)',
                ) ?? null
        }

        return revealed
    }

    const focusEntryPoint = () => {
        const target = entryTarget()

        if (!target || document.activeElement === target) {
            return
        }

        const doFocus = () => {
            target.focus({ preventScroll: false })
            target.select?.()
        }

        if (revealEntryPoint(target)) {
            requestAnimationFrame(doFocus)
        } else {
            doFocus()
        }
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

    // ----- usage learning (field names only — values are never read) -----

    const QUEUE_KEY = 'formsettings-usage'

    const learnRoot = () => document.querySelector('[data-formsettings-learn]')

    let run = null

    const noteInteraction = (event) => {
        const root = learnRoot()

        if (!root) {
            return
        }

        const field = event.target?.closest?.('[data-formsettings-name]')
        const name = field?.getAttribute('data-formsettings-name')
        const key = root.getAttribute('data-formsettings-formkey')

        if (!name || !key) {
            return
        }

        if (!run || run.key !== key) {
            run = { key, touched: [], first: null, action: null }
        }

        if (run.first === null) {
            run.first = name
        }

        if (!run.touched.includes(name)) {
            run.touched.push(name)
        }
    }

    document.addEventListener('input', noteInteraction, { capture: true })
    document.addEventListener('change', noteInteraction, { capture: true })

    const readQueue = () => {
        try {
            return JSON.parse(localStorage.getItem(QUEUE_KEY)) ?? []
        } catch {
            return []
        }
    }

    // A run only counts once the form is actually submitted. The queue
    // lives in localStorage because submitting navigates away — it is
    // flushed to the server on the next page that shows the gear.
    const queueRun = () => {
        if (!run || run.touched.length === 0) {
            return
        }

        try {
            localStorage.setItem(
                QUEUE_KEY,
                JSON.stringify([...readQueue(), run].slice(-20)),
            )
        } catch {
            // Storage may be full or blocked — losing a run is fine.
        }

        run = null
    }

    window.addEventListener(
        'click',
        (event) => {
            if (event.target?.closest?.('[data-formsettings-back]')) {
                if (run) {
                    run.action = 'back'
                }

                queueRun()

                return
            }

            if (event.target?.closest?.('[data-formsettings-primary]')) {
                queueRun()
            }
        },
        { capture: true },
    )

    const flushQueue = () => {
        if (!learnRoot() || !window.Livewire?.dispatch) {
            return
        }

        const queue = readQueue()

        if (queue.length === 0) {
            return
        }

        try {
            localStorage.removeItem(QUEUE_KEY)
        } catch {}

        window.Livewire.dispatch('formsettings-usage', { runs: queue })
    }

    document.addEventListener('livewire:initialized', () =>
        setTimeout(flushQueue, 0),
    )

    // Size the settings dropdown inline — theme stylesheets load after
    // plugin assets and win every cascade fight (layered !important),
    // so CSS alone cannot widen the panel reliably.
    const sizePanel = () => {
        const panel = document.querySelector(
            '.fi-dropdown-panel.formsettings-width',
        )

        if (!panel) {
            return
        }

        const width = window.matchMedia('(min-width: 1024px)').matches
            ? '38rem'
            : '24rem'

        // 'important': themes size .fi-dropdown-panel with layered
        // !important rules that beat plain inline styles.
        panel.style.setProperty('width', width, 'important')
        panel.style.setProperty(
            'max-width',
            `min(${width}, calc(100vw - 2rem))`,
            'important',
        )
    }

    window.addEventListener('resize', sizePanel)

    // The visible "save & back" button is rendered hidden inside the
    // gear's header markup — move it next to the primary form action.
    // Livewire morphs may restore it to its original spot, so this
    // runs again after every morph.
    const placeBackButton = () => {
        const buttons = [
            ...document.querySelectorAll('[data-formsettings-back]'),
        ]
        const primary = primaryButton()

        if (!primary || buttons.length === 0) {
            return
        }

        let target = buttons.find(
            (button) => button.parentElement === primary.parentElement,
        )

        if (!target) {
            target = buttons[0]
            primary.insertAdjacentElement('afterend', target)
        }

        buttons
            .filter((button) => button !== target)
            .forEach((button) => button.remove())

        target.hidden = false
    }

    // ----- arrange overlay: number the fields on the real form -----
    //
    // Click a field's badge to append it to the chain (badges show the
    // position, arrows connect consecutive picks); click a numbered
    // badge to undo back to that point. Star sets the entry point, the
    // crossed eye hides a field. Apply sends the chain to the panel,
    // where the subset merge keeps unclicked fields in place.

    const arrange = { active: false, chain: [], layer: null, timer: null }

    const arrangeLabel = (key) =>
        document
            .querySelector('[data-formsettings-formkey]')
            ?.getAttribute('data-formsettings-arrange-' + key) ?? key

    const exitArrange = (commit) => {
        if (!arrange.active) {
            return
        }

        const chain = [...arrange.chain]

        arrange.active = false
        clearInterval(arrange.timer)
        arrange.layer?.remove()
        arrange.layer = null
        arrange.chain = []

        if (commit && chain.length > 0 && window.Livewire?.dispatch) {
            window.Livewire.dispatch('formsettings-arrange', { names: chain })
        }
    }

    const renderArrange = () => {
        if (!arrange.active || !arrange.layer) {
            return
        }

        const svg = arrange.layer.querySelector('svg')
        const badges = arrange.layer.querySelector('[data-badges]')
        badges.innerHTML = ''
        const points = []
        // Multi-input fields (toggle buttons, checkbox lists, …) carry
        // the name on every input — one badge per field is enough.
        const seen = new Set()

        for (const el of document.querySelectorAll(
            '[data-formsettings-name]',
        )) {
            const rect = el.getBoundingClientRect()

            if (rect.width === 0 || rect.height === 0) {
                continue
            }

            const name = el.getAttribute('data-formsettings-name')

            if (seen.has(name)) {
                continue
            }

            seen.add(name)
            const index = arrange.chain.indexOf(name)

            const cluster = document.createElement('div')
            cluster.className = 'formsettings-arrange-cluster'
            cluster.style.left = `${Math.max(rect.left - 34, 4)}px`
            cluster.style.top = `${rect.top + rect.height / 2 - 14}px`

            const badge = document.createElement('button')
            badge.type = 'button'
            badge.className =
                'formsettings-arrange-badge' +
                (index >= 0 ? ' formsettings-arrange-active' : '')
            badge.textContent = index >= 0 ? String(index + 1) : ''
            badge.addEventListener('click', (event) => {
                event.stopPropagation()
                const position = arrange.chain.indexOf(name)
                if (position >= 0) {
                    arrange.chain = arrange.chain.slice(0, position)
                } else {
                    arrange.chain.push(name)
                }
                renderArrange()
            })
            cluster.appendChild(badge)

            const star = document.createElement('button')
            star.type = 'button'
            star.className = 'formsettings-arrange-mini'
            star.textContent = el.closest('[data-formsettings-entry]') || el.hasAttribute('data-formsettings-entry') ? '★' : '☆'
            star.addEventListener('click', (event) => {
                event.stopPropagation()
                window.Livewire?.dispatch('formsettings-overlay-entry', {
                    name,
                })
            })
            cluster.appendChild(star)

            const eye = document.createElement('button')
            eye.type = 'button'
            eye.className = 'formsettings-arrange-mini'
            eye.textContent = '⊘'
            eye.addEventListener('click', (event) => {
                event.stopPropagation()
                arrange.chain = arrange.chain.filter((n) => n !== name)
                window.Livewire?.dispatch('formsettings-overlay-hide', {
                    name,
                })
            })
            cluster.appendChild(eye)

            badges.appendChild(cluster)

            if (index >= 0) {
                points[index] = {
                    x: rect.left - 20,
                    y: rect.top + rect.height / 2,
                }
            }
        }

        svg.setAttribute(
            'viewBox',
            `0 0 ${window.innerWidth} ${window.innerHeight}`,
        )
        svg.innerHTML = ''

        for (let i = 1; i < points.length; i++) {
            const from = points[i - 1]
            const to = points[i]

            if (!from || !to) {
                continue
            }

            const line = document.createElementNS(
                'http://www.w3.org/2000/svg',
                'line',
            )
            line.setAttribute('x1', from.x)
            line.setAttribute('y1', from.y)
            line.setAttribute('x2', to.x)
            line.setAttribute('y2', to.y)
            line.setAttribute('class', 'formsettings-arrange-line')
            svg.appendChild(line)
        }
    }

    const startArrange = () => {
        if (arrange.active) {
            return
        }

        document.body.click() // closes the gear dropdown

        arrange.active = true
        arrange.chain = []

        const layer = document.createElement('div')
        layer.className = 'formsettings-arrange-layer'
        layer.innerHTML =
            '<svg></svg><div data-badges></div>' +
            '<div class="formsettings-arrange-bar">' +
            '<button type="button" data-arrange-apply></button>' +
            '<button type="button" data-arrange-cancel></button>' +
            '</div>'

        layer.querySelector('[data-arrange-apply]').textContent =
            arrangeLabel('apply')
        layer.querySelector('[data-arrange-cancel]').textContent =
            arrangeLabel('cancel')
        layer
            .querySelector('[data-arrange-apply]')
            .addEventListener('click', () => exitArrange(true))
        layer
            .querySelector('[data-arrange-cancel]')
            .addEventListener('click', () => exitArrange(false))

        document.body.appendChild(layer)
        arrange.layer = layer
        arrange.timer = setInterval(renderArrange, 250)
        renderArrange()
    }

    window.addEventListener('formsettings-arrange-start', startArrange)

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            exitArrange(false)
        }
    })

    window.addEventListener(
        'scroll',
        () => {
            if (arrange.active) {
                renderArrange()
            }
        },
        { capture: true, passive: true },
    )

    // ----- page lifecycle -----

    // Open the form on the user's start tab — unless an entry point is
    // set, which activates its own tab and takes precedence.
    const revealStartTab = () => {
        if (entryTarget()) {
            return
        }

        const field = document.querySelector('[data-formsettings-start-tab]')

        if (field) {
            revealEntryPoint(field)
        }
    }

    const onPageReady = () => {
        userInteracted = false
        guardUntil = performance.now() + 2500
        run = null
        exitArrange(false)
        applyLabel()
        sizePanel()
        placeBackButton()
        revealStartTab()
        focusEntryPoint()
        setTimeout(focusEntryPoint, 150)
        setTimeout(flushQueue, 0)
    }

    document.addEventListener('livewire:init', () => {
        window.Livewire.hook('morphed', () =>
            queueMicrotask(() => {
                applyLabel()
                placeBackButton()
                sizePanel()
            }),
        )
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
