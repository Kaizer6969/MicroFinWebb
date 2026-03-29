class Tour {
    constructor(steps, options = {}) {
        this.steps = steps;
        this.options = options; // Expect { storageKey: 'some_key', useDatabase: true }
        this.currentStep = 0;
        this.overlay = null;
        this.tooltip = null;
        this.popperInstance = null;
        this.isActive = false;

        this.init();
    }

    init() {
        // Specific check: if storageKey is provided, check if already seen in localStorage
        if (this.options.storageKey && localStorage.getItem(this.options.storageKey)) {
            return; // Do nothing if already seen
        }

        this.createOverlay();
        this.createTooltip();
    }

    createOverlay() {
        if (document.querySelector('.tour-overlay')) {
            this.overlay = document.querySelector('.tour-overlay');
            return;
        }
        this.overlay = document.createElement('div');
        this.overlay.className = 'tour-overlay';
        document.body.appendChild(this.overlay);
    }

    createTooltip() {
        if (document.querySelector('.tour-tooltip')) {
            this.tooltip = document.querySelector('.tour-tooltip');
            return;
        }

        this.tooltip = document.createElement('div');
        this.tooltip.className = 'tour-tooltip';
        this.tooltip.innerHTML = `
            <div class="tour-arrow" data-popper-arrow></div>
            <div class="tour-header">
                <div class="tour-title"></div>
            </div>
            <div class="tour-content"></div>
            <div class="tour-footer">
                <div class="tour-progress"></div>
                <div class="tour-actions">
                    <button class="tour-btn tour-btn-skip">Skip</button>
                    <button class="tour-btn tour-btn-next">Next</button>
                </div>
            </div>
        `;
        document.body.appendChild(this.tooltip);

        // Bind events
        this.tooltip.querySelector('.tour-btn-next').addEventListener('click', () => this.nextStep());
        this.tooltip.querySelector('.tour-btn-skip').addEventListener('click', () => this.endTour());
    }

    start() {
        // Double check storage key at start just in case init was called but start was delayed
        if (this.options.storageKey && localStorage.getItem(this.options.storageKey)) {
            console.log('Tour already seen (' + this.options.storageKey + ')');
            return;
        }

        if (this.steps.length === 0) return;
        this.isActive = true;
        this.currentStep = 0;

        // Ensure overlay exists (in case it wasn't created in init due to conditions)
        if (!this.overlay) this.createOverlay();
        if (!this.tooltip) this.createTooltip();

        this.overlay.classList.add('active');
        this.showStep(this.currentStep);
    }

    showStep(index) {
        if (index >= this.steps.length) {
            this.endTour(true);
            return;
        }

        const step = this.steps[index];
        const target = document.querySelector(step.target);

        if (!target) {
            console.warn(`Tour target not found: ${step.target}`);
            if (index < this.steps.length - 1) {
                this.nextStep();
            } else {
                this.endTour(true);
            }
            return;
        }

        // Highlight target
        this.clearHighlights();
        target.classList.add('tour-highlight');

        // Scroll to target
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // Update Tooltip Content
        this.tooltip.querySelector('.tour-title').textContent = step.title;
        this.tooltip.querySelector('.tour-content').textContent = step.content;

        // Update Buttons
        const nextBtn = this.tooltip.querySelector('.tour-btn-next');
        nextBtn.textContent = index === this.steps.length - 1 ? 'Finish' : 'Next';

        // Update Progress Dots
        const progressContainer = this.tooltip.querySelector('.tour-progress');
        progressContainer.innerHTML = '';
        this.steps.forEach((_, i) => {
            const dot = document.createElement('div');
            dot.className = `tour-dot ${i === index ? 'active' : ''}`;
            progressContainer.appendChild(dot);
        });

        // Position Tooltip
        this.tooltip.classList.add('active');

        // Cleanup old popper
        if (this.popperInstance) {
            this.popperInstance.destroy();
            this.popperInstance = null;
        }

        // Check if Popper is available
        let popperCreator = null;
        if (window.Popper && window.Popper.createPopper) popperCreator = window.Popper.createPopper;
        else if (window.bootstrap && window.bootstrap.Popper && window.bootstrap.Popper.createPopper) popperCreator = window.bootstrap.Popper.createPopper;

        if (popperCreator) {
            this.popperInstance = popperCreator(target, this.tooltip, {
                placement: step.placement || 'bottom',
                modifiers: [
                    { name: 'offset', options: { offset: [0, 15] } },
                    { name: 'arrow', options: { element: '[data-popper-arrow]' } }
                ],
            });
        } else {
            // Simple fallback positioning
            const rect = target.getBoundingClientRect();
            this.tooltip.style.top = (rect.bottom + window.scrollY + 10) + 'px';
            this.tooltip.style.left = (rect.left + window.scrollX) + 'px';
        }
    }

    nextStep() {
        this.currentStep++;
        this.showStep(this.currentStep);
    }

    clearHighlights() {
        document.querySelectorAll('.tour-highlight').forEach(el => {
            el.classList.remove('tour-highlight');
        });
    }

    endTour(completed = false) {
        this.isActive = false;
        if (this.overlay) this.overlay.classList.remove('active');
        if (this.tooltip) this.tooltip.classList.remove('active');
        this.clearHighlights();

        if (this.popperInstance) {
            this.popperInstance.destroy();
            this.popperInstance = null;
        }

        if (completed) {
            this.markAsSeen();
        }
    }

    markAsSeen() {
        // If storageKey is provided, use localStorage
        if (this.options.storageKey) {
            localStorage.setItem(this.options.storageKey, 'true');
        }

        // If configured to hit DB (e.g. main dashboard tour)
        if (this.options.useDatabase) {
            fetch('../includes/update_tour_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ seen: true })
            })
                .then(response => response.json())
                .then(data => console.log('Tour status updated', data))
                .catch(err => console.error('Error updating tour status', err));
        }
    }
}
