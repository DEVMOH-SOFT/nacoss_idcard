(() => {
    const imageInput = document.getElementById('image');
    const preview = document.getElementById('preview');
    const warning = document.getElementById('imageWarning');
    const photoInstructionAck = document.getElementById('photoInstructionAck');
    const matricInput = document.getElementById('matric_no');
    const matricStatus = document.getElementById('matricStatus');
    const checkMatricBtn = document.getElementById('checkMatricBtn');
    const detailsFieldset = document.getElementById('detailsFieldset');
    const formModeInput = document.getElementById('form_mode');
    const studentIdInput = document.getElementById('student_id');
    const fullNameInput = document.getElementById('full_name');
    const levelInput = document.getElementById('level');
    const checkAll = document.getElementById('checkAll');
    const checks = document.querySelectorAll('.studentCheck');
    const studentForm = document.getElementById('studentForm');
    const lookupUrl = studentForm?.dataset.lookupUrl || 'lookup_student.php';

    let confirmedSubmit = false;
    let lastCheckedMatric = '';

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

    const setMatricStatus = (message, type = 'info') => {
        if (!matricStatus) return;
        matricStatus.textContent = message;
        matricStatus.className = type === 'error'
            ? 'warning error-text'
            : type === 'success'
                ? 'warning success-text'
                : 'warning';
    };

    const clearPreview = () => {
        if (!preview) return;
        preview.hidden = true;
        preview.removeAttribute('src');
    };

    const resetImageSection = () => {
        if (photoInstructionAck) {
            photoInstructionAck.checked = false;
        }
        if (imageInput) {
            imageInput.value = '';
        }
        clearPreview();
    };

    const setImageMode = (mode) => {
        if (!imageInput || !photoInstructionAck) return;
        const isCreateMode = mode === 'create';
        imageInput.required = isCreateMode;
        photoInstructionAck.required = isCreateMode;
        imageInput.disabled = true;

        warning.textContent = isCreateMode
            ? 'Read and tick the instruction checkbox before uploading your image.'
            : 'Tick the instruction checkbox only if you want to replace your photo.';
        warning.className = 'warning';
    };

    const clearEditableFields = () => {
        if (fullNameInput) fullNameInput.value = '';
        if (levelInput) levelInput.value = '';
        resetImageSection();
    };

    const lockDetails = () => {
        if (detailsFieldset) {
            detailsFieldset.disabled = true;
        }
        if (formModeInput) {
            formModeInput.value = '';
        }
        if (studentIdInput) {
            studentIdInput.value = '';
        }
        setImageMode('');
        refreshSubmitState();
    };

    const unlockDetails = (mode) => {
        if (detailsFieldset) {
            detailsFieldset.disabled = false;
        }
        if (formModeInput) {
            formModeInput.value = mode;
        }
        setImageMode(mode);
        refreshSubmitState();
    };

    const fillStudentDetails = (student) => {
        if (fullNameInput) fullNameInput.value = student.full_name || '';
        if (levelInput) levelInput.value = student.level || '';
        resetImageSection();
    };

    const normalizeMatric = () => {
        if (!matricInput) return '';
        const normalized = matricInput.value.trim().toUpperCase();
        matricInput.value = normalized;
        return normalized;
    };

    const resetAfterMatricChange = () => {
        if (!matricInput) return;
        const currentMatric = matricInput.value.trim().toUpperCase();
        if (currentMatric === lastCheckedMatric) return;

        confirmedSubmit = false;
        clearEditableFields();
        lockDetails();
        setMatricStatus('Enter your matric number first, then click "Check Matric" to continue.');
    };

    const syncImageUploadGate = () => {
        if (!photoInstructionAck || !imageInput || !warning) return;
        const isCreateMode = formModeInput?.value === 'create';
        const canUpload = photoInstructionAck.checked;
        imageInput.disabled = !canUpload;

        if (!canUpload) {
            imageInput.value = '';
            clearPreview();
            warning.textContent = isCreateMode
                ? 'Read and tick the instruction checkbox before uploading your image.'
                : 'Tick the instruction checkbox only if you want to replace your photo.';
            warning.className = 'warning';
        } else {
            warning.textContent = 'Allowed image types: JPG, JPEG, PNG, WEBP. Max size: 2MB. Must be transparent background.';
            warning.className = 'warning';
        }

        refreshSubmitState();
    };

    const handleMatricCheck = async () => {
        const matricNo = normalizeMatric();
        if (!matricNo) {
            setMatricStatus('Enter your matric number first.', 'error');
            lockDetails();
            return;
        }

        confirmedSubmit = false;
        setMatricStatus('Checking matric number...');
        if (checkMatricBtn) {
            checkMatricBtn.disabled = true;
            checkMatricBtn.textContent = 'Checking...';
        }

        try {
            const response = await fetch(`${lookupUrl}?matric_no=${encodeURIComponent(matricNo)}`, {
                headers: { Accept: 'application/json' }
            });
            const payload = await response.json();

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Unable to check matric number right now.');
            }

            lastCheckedMatric = matricNo;

            if (!payload.exists) {
                clearEditableFields();
                unlockDetails('create');
                setMatricStatus(payload.message, 'success');
                if (fullNameInput) fullNameInput.focus();
                syncImageUploadGate();
                return;
            }

            const shouldEdit = window.confirm('This matric number already exists. Would you like to edit your details?');
            if (!shouldEdit) {
                lockDetails();
                setMatricStatus('This matric number already exists. Form locked because you chose not to edit the existing details.', 'error');
                return;
            }

            fillStudentDetails(payload.student || {});
            if (studentIdInput) {
                studentIdInput.value = payload.student?.id || '';
            }
            unlockDetails('edit');
            setMatricStatus('Existing details loaded. You can edit your information now. Post remains unchanged.', 'success');
            if (fullNameInput) fullNameInput.focus();
            syncImageUploadGate();
        } catch (error) {
            lockDetails();
            setMatricStatus(error.message || 'Unable to check matric number right now.', 'error');
        } finally {
            if (checkMatricBtn) {
                checkMatricBtn.disabled = false;
                checkMatricBtn.textContent = 'Check Matric';
            }
            refreshSubmitState();
        }
    };

    if (imageInput && warning) {
        if (photoInstructionAck) {
            photoInstructionAck.addEventListener('change', syncImageUploadGate);
        }

        imageInput.addEventListener('change', () => {
            const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;

            if (!file) {
                clearPreview();
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
                clearPreview();
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
        const requiredFields = Array.from(studentForm.querySelectorAll('[required]'));
        requiredFields.forEach((field) => {
            field.addEventListener('input', refreshSubmitState);
            field.addEventListener('change', refreshSubmitState);
        });

        if (matricInput) {
            matricInput.addEventListener('input', resetAfterMatricChange);
            matricInput.addEventListener('blur', normalizeMatric);
            matricInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    handleMatricCheck();
                }
            });
        }

        if (checkMatricBtn) {
            checkMatricBtn.addEventListener('click', handleMatricCheck);
        }

        lockDetails();
        setMatricStatus('Enter your matric number first, then click "Check Matric" to continue.');
        refreshSubmitState();

        studentForm.addEventListener('submit', (event) => {
            if (confirmedSubmit) return;
            event.preventDefault();
            refreshSubmitState();
            const submitButton = studentForm.querySelector('button[type="submit"]');
            if (submitButton && submitButton.disabled) return;
            if (!formModeInput?.value) {
                setMatricStatus('Check your matric number before submitting the form.', 'error');
                return;
            }

            const fullName = (fullNameInput?.value || '').trim();
            const levelText = levelInput && levelInput.selectedIndex >= 0 ? levelInput.options[levelInput.selectedIndex].text : '';
            const matricNo = (matricInput?.value || '').trim();
            const isEditMode = formModeInput.value === 'edit';
            const imageName = imageInput && imageInput.files && imageInput.files[0]
                ? imageInput.files[0].name
                : isEditMode
                    ? 'Keep existing photo'
                    : 'No file selected';

            const confirmationText = [
                isEditMode ? 'Please confirm your updated details:' : 'Please confirm your details:',
                '',
                'Full Name: ' + fullName,
                'Level: ' + levelText,
                'Matric Number: ' + matricNo,
                'Photo: ' + imageName,
                '',
                'Click OK to confirm or Cancel to edit.'
            ].join('\n');
            if (!window.confirm(confirmationText)) return;

            confirmedSubmit = true;
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = isEditMode ? 'Saving...' : 'Submitting...';
            }
            studentForm.submit();
        });
    }
})();
