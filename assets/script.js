(() => {
    const imageInput = document.getElementById('image');
    const preview = document.getElementById('preview');
    const warning = document.getElementById('imageWarning');
    const photoInstructionAck = document.getElementById('photoInstructionAck');
    const checkAll = document.getElementById('checkAll');
    const checks = document.querySelectorAll('.studentCheck');
    const studentForm = document.getElementById('studentForm');

    const isFieldFilled = (field) => {
        if (!field) return true;
        if (field.disabled) return true;
        if (field.type === 'checkbox' || field.type === 'radio') return field.checked;
        if (field.type === 'file') return !!(field.files && field.files.length > 0);
        return field.value.trim() !== '';
    };

    const refreshSubmitState = () => {
        if (!studentForm) return;
        const submitButton = studentForm.querySelector('button[type="submit"]');
        if (!submitButton) return;

        const requiredFields = Array.from(studentForm.querySelectorAll('[required]'));
        const allFilled = requiredFields.every((field) => isFieldFilled(field));
        submitButton.disabled = !allFilled;
    };

    if (imageInput && warning) {
        const syncImageUploadGate = () => {
            if (!photoInstructionAck) return;
            const canUpload = photoInstructionAck.checked;
            imageInput.disabled = !canUpload;

            if (!canUpload) {
                imageInput.value = '';
                if (preview) {
                    preview.hidden = true;
                    preview.removeAttribute('src');
                }
                warning.textContent = 'Read and tick the instruction checkbox before uploading your image.';
                warning.className = 'warning';
            } else {
                warning.textContent = 'Allowed image types: JPG, JPEG, PNG, WEBP. Max size: 2MB. Must be transparent background.';
                warning.className = 'warning';
            }

            refreshSubmitState();
        };

        if (photoInstructionAck) {
            photoInstructionAck.addEventListener('change', syncImageUploadGate);
            syncImageUploadGate();
        }

        imageInput.addEventListener('change', () => {
            const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;

            if (!file) {
                if (preview) {
                    preview.hidden = true;
                    preview.removeAttribute('src');
                }
                warning.textContent = 'Allowed image types: JPG, JPEG, PNG, WEBP. Max size: 2MB. Must be transparent background.';
                warning.className = 'warning';
                refreshSubmitState();
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const tooLarge = file.size > (2 * 1024 * 1024);
            const badType = !allowedTypes.includes(file.type);

            if (tooLarge || badType) {
                warning.textContent = badType ? 'Invalid image type. Use JPG, JPEG, PNG, or WEBP.' : 'Image too large. Maximum size is 2MB.';
                warning.className = 'warning error-text';
                imageInput.value = '';
                if (preview) {
                    preview.hidden = true;
                    preview.removeAttribute('src');
                }
                refreshSubmitState();
                return;
            }

            warning.textContent = 'Image selected successfully. Ensure it follows the photo rules before submitting.';
            warning.className = 'warning success-text';

            if (preview) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    preview.src = event.target.result;
                    preview.hidden = false;
                };
                reader.readAsDataURL(file);
            }

            refreshSubmitState();
        });
    }

    if (checkAll && checks.length > 0) {
        checkAll.addEventListener('change', () => {
            checks.forEach((box) => {
                box.checked = checkAll.checked;
            });
        });
    }

    if (studentForm) {
        let confirmedSubmit = false;
        const requiredFields = Array.from(studentForm.querySelectorAll('[required]'));
        requiredFields.forEach((field) => {
            field.addEventListener('input', refreshSubmitState);
            field.addEventListener('change', refreshSubmitState);
        });
        refreshSubmitState();

        studentForm.addEventListener('submit', (event) => {
            if (confirmedSubmit) return;
            event.preventDefault();
            refreshSubmitState();
            const submitButton = studentForm.querySelector('button[type="submit"]');
            if (submitButton && submitButton.disabled) return;

            const fullName = (document.getElementById('full_name')?.value || '').trim();
            const levelSelect = document.getElementById('level');
            const levelText = levelSelect && levelSelect.selectedIndex >= 0 ? levelSelect.options[levelSelect.selectedIndex].text : '';
            const matricNo = (document.getElementById('matric_no')?.value || '').trim();
            const imageName = imageInput && imageInput.files && imageInput.files[0] ? imageInput.files[0].name : 'No file selected';

            const confirmationText = ['Please confirm your details:', '', 'Full Name: ' + fullName, 'Level: ' + levelText, 'Matric Number: ' + matricNo, 'Photo: ' + imageName, '', 'Click OK to confirm or Cancel to edit.'].join('\n');
            if (!window.confirm(confirmationText)) return;

            confirmedSubmit = true;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Submitting...';
            }
            studentForm.submit();
        });
    }
})();
