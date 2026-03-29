<script>
    // Verification Modal Logic
    
    function closeVerificationModal() {
        const modal = document.getElementById('verificationModal');
        if (modal) {
            modal.classList.remove('show');
            // Optional: Reset form or steps if needed, but keeping state might be better for UX if they accidentally closed it
        }
    }

    function goToStep2() {
        // Validate Step 1
        const step1 = document.getElementById('step1');
        const inputs = step1.querySelectorAll('input[required], select[required]');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.classList.add('is-invalid');
                // Remove invalid class on input
                input.addEventListener('input', function() {
                    this.classList.remove('is-invalid');
                }, {once: true});
            }
        });

        if (!isValid) {
            // Shake animation or toast could be added here
            return;
        }

        // Switch to Step 2
        document.getElementById('step1').classList.remove('active');
        document.getElementById('step1-indicator').classList.remove('active');
        document.getElementById('step1-indicator').classList.add('completed');
        
        document.getElementById('step2').classList.add('active');
        document.getElementById('step2-indicator').classList.add('active');
    }

    function goToStep1() {
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step2-indicator').classList.remove('active');
        
        document.getElementById('step1').classList.add('active');
        document.getElementById('step1-indicator').classList.add('active');
        document.getElementById('step1-indicator').classList.remove('completed');
    }

    // Image Preview Logic
    window.previewImage = function(input, previewId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewContainer = document.getElementById(previewId);
                const img = previewContainer.querySelector('img');
                img.src = e.target.result;
                
                previewContainer.classList.remove('d-none');
                
                // Hide placeholder
                const placeholderId = previewId.replace('preview', 'placeholder');
                document.getElementById(placeholderId).classList.add('d-none');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    window.clearPreview = function(inputId, previewId) {
        const input = document.getElementById(inputId);
        input.value = ''; // Clear file input
        
        const previewContainer = document.getElementById(previewId);
        previewContainer.classList.add('d-none');
        
        // Show placeholder
        const placeholderId = previewId.replace('preview', 'placeholder');
        document.getElementById(placeholderId).classList.remove('d-none');
    }
</script>

