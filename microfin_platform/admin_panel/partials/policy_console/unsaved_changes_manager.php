<!-- Unsaved Changes Prompt Modal -->
<div id="unsaved-changes-backdrop" class="unsaved-changes-backdrop"></div>
<div id="unsaved-changes-modal" class="unsaved-changes-modal">
    <div class="unsaved-changes-content">
        <h3 style="margin-top: 0; margin-bottom: 8px; color: var(--text-main); font-size: 18px; display: flex; align-items: center; gap: 10px;">
            <svg style="width: 22px; height: 22px; fill: #f59e0b;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
            Unsaved Changes
        </h3>
        <p style="color: var(--text-muted); font-size: 14px; line-height: 1.5; margin-bottom: 24px; padding-left: 32px;">
            You have unsaved edits. If you leave now, they will be lost.
        </p>
        <div class="unsaved-changes-actions">
            <button type="button" id="unsaved-cancel-btn" class="btn btn-secondary" style="border-radius: 20px; font-size: 13px; font-weight: 500;">Cancel</button>
            <button type="button" id="unsaved-discard-btn" class="btn btn-danger" style="background-color: var(--danger-color, #dc2626); color: white; border: none; border-radius: 20px; font-size: 13px; font-weight: 600;">Discard Changes</button>
            <button type="button" id="unsaved-save-btn" class="btn btn-primary" style="background-color: var(--primary-color, #3b82f6); color: white; border: none; border-radius: 20px; font-size: 13px; font-weight: 600; padding: 10px 20px; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);">Save</button>
        </div>
    </div>
</div>

<style>
.unsaved-changes-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0, 0, 0, 0.6); /* Slightly darker */
    backdrop-filter: grayscale(100%) blur(4px); /* Stronger blur and grayscale */
    -webkit-backdrop-filter: grayscale(100%) blur(4px);
    z-index: 2147483646; /* Maximum z-index possible to cover EVERYTHING including sidebar and navbar */
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}
.unsaved-changes-backdrop.is-active {
    opacity: 1;
    visibility: visible;
}
.unsaved-changes-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -45%) scale(0.95);
    background: var(--bg-card, #ffffff);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.3), 0 0 0 1px rgba(0,0,0,0.05);
    z-index: 2147483647; /* Modal on top of everything */
    width: 90%;
    max-width: 480px;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.2s;
}
.unsaved-changes-modal.is-active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
    transform: translate(-50%, -50%) scale(1);
}
.unsaved-changes-actions {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
}
.btn-secondary {
    background: transparent;
    border: 1px solid var(--border-color);
    color: var(--text-main);
    padding: 10px 16px;
    cursor: pointer;
}
.btn-secondary:hover {
    background: var(--bg-body);
}
</style>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const storedForms = new Map();
    let hasUnsavedChanges = false;
    let pendingNavTargetUrl = null;

    function serializeForm(formElement) {
        const formData = new FormData(formElement);
        const dataObj = {};
        for (const [key, value] of formData.entries()) {
            if (!dataObj[key]) dataObj[key] = [];
            dataObj[key].push(typeof value === "string" ? value.trim() : value);
        }
        const ordered = {};
        Object.keys(dataObj).sort().forEach(key => ordered[key] = dataObj[key].sort());
        return JSON.stringify(ordered);
    }

    const policyPanels = document.querySelectorAll(".credit-policy-tab-panel form");
    policyPanels.forEach(form => {
        setTimeout(() => storedForms.set(form.id, serializeForm(form)), 500);

        const lazyCheck = () => {
            const originalState = storedForms.get(form.id);
            if (!originalState) return;
            const currentState = serializeForm(form);
            const wasDirty = hasUnsavedChanges;
            hasUnsavedChanges = (originalState !== currentState);
            
            // Visual indicator on save button globally
            const saveBtn = document.getElementById("global-save-policy-btn");
            if (saveBtn) {
                if (hasUnsavedChanges) {
                    saveBtn.style.transform = "scale(1.05)";
                    saveBtn.style.boxShadow = "0 6px 16px rgba(59, 130, 246, 0.5)";
                    setTimeout(() => { saveBtn.style.transform = ""; }, 300);
                } else {
                    saveBtn.style.boxShadow = "";
                }
            }
        };

        form.addEventListener("input", lazyCheck);
        form.addEventListener("change", lazyCheck);
    });

    const modal = document.getElementById("unsaved-changes-modal");
    const backdrop = document.getElementById("unsaved-changes-backdrop");

    // Automatically move backdrop and modal to document.body to break out of container restrictions
    if (backdrop && modal) {
        document.body.appendChild(backdrop);
        document.body.appendChild(modal);
    }

    function showModal(url) {
        pendingNavTargetUrl = url;
        if (backdrop) backdrop.classList.add("is-active");
        modal.classList.add("is-active");
    }

    function hideModal() {
        modal.classList.remove("is-active");
        if (backdrop) backdrop.classList.remove("is-active");
        pendingNavTargetUrl = null;
    }

    document.getElementById("unsaved-cancel-btn").addEventListener("click", hideModal);

    document.getElementById("unsaved-discard-btn").addEventListener("click", () => {
        hasUnsavedChanges = false; // Trust the discard
        if (pendingNavTargetUrl) {
            window.location.href = pendingNavTargetUrl;
        } else {
            window.location.reload();
        }
    });

    document.getElementById("unsaved-save-btn").addEventListener("click", () => {
        hideModal();
        const activeForm = document.querySelector(".credit-policy-tab-panel:not([hidden]) form");
        if(activeForm) {
            hasUnsavedChanges = false;
            
            // Re-render button logic natively
            const globalSaveBtn = document.getElementById("global-save-policy-btn");
            if(globalSaveBtn) {
                globalSaveBtn.innerHTML = "<i class=\"fas fa-spinner fa-spin\" style=\"margin-right: 6px;\"></i><span>Saving...</span>";
                globalSaveBtn.style.pointerEvents = "none";
            }
            activeForm.submit();
        }
    });

    document.body.addEventListener("click", (e) => {
        const link = e.target.closest("a");
        if (!link) return;
        if (link.getAttribute("target") === "_blank" || link.hasAttribute("download") || link.href.includes("javascript:void(0)")) return;

        // If the link is just a hash (tab switching or same-page anchor), don't show the modal
        if (link.getAttribute("href").startsWith("#") || link.href.includes(window.location.pathname + "#")) return;

        // Allow free navigation between Policy Console tabs (Overview, Credit & Limits, Rules & Requirements, Required Documents)
        if (link.hasAttribute("data-credit-policy-subtab") || link.href.includes("tab=credit_control_policy") || link.href.includes("credit_policy_tab=")) {
            // Disarm beforeunload if they're deliberately navigating within the console
            hasUnsavedChanges = false;
            return;
        }

        if (hasUnsavedChanges) {
            e.preventDefault();
            e.stopPropagation();
            showModal(link.href);
        }
    }, true);

    window.addEventListener("beforeunload", (e) => {
        if (hasUnsavedChanges && !pendingNavTargetUrl) {
            e.preventDefault();
            e.returnValue = "";
            return "";
        }
    });
});
</script>
