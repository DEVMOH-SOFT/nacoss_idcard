(() => {
    const imageInput = document.getElementById('image');
    const preview = document.getElementById('preview');
    const warning = document.getElementById('imageWarning');
    const photoInstructionAck = document.getElementById('photoInstructionAck');
    const checkAll = document.getElementById('checkAll');
    const checks = document.querySelectorAll('.studentCheck');
    const studentForm = document.getElementById('studentForm');

    // Models will be loaded from this CDN
    const MODEL_URL = 'https://cdn.jsdelivr.net/gh/vladmandic/face-api@master/model/';
    let modelsLoaded = false;

    const loadModels = async () => {
        if (typeof faceapi === 'undefined') return;
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL)
            ]);
            modelsLoaded = true;
            console.log('AI Models Loaded');
        } catch (e) {
            console.error('Failed to load AI models', e);
        }
    };

    // Start loading AI models immediately
    loadModels();

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
                warning.classList.remove('error-text', 'success-text');
            } else {
                warning.textContent = 'Allowed image types: JPG, JPEG, PNG, WEBP. Max size: 2MB. Must be transparent background.';
                warning.classList.remove('error-text', 'success-text');
            }
            refreshSubmitState();
        };

        if (photoInstructionAck) {
            photoInstructionAck.addEventListener('change', syncImageUploadGate);
            syncImageUploadGate();
        }

        const analyzeImage = (file) => {
            return new Promise((resolve) => {
                const img = new Image();
                img.onload = async () => {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    ctx.drawImage(img, 0, 0);

                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
                    let transparentPixels = 0;
                    const totalPixels = canvas.width * canvas.height;

                    const step = Math.max(1, Math.floor(totalPixels / 5000));
                    for (let i = 3; i < imageData.length; i += 4 * step) {
                        if (imageData[i] < 200) transparentPixels++;
                    }

                    const transparencyRatio = transparentPixels / (totalPixels / step);
                    const aspectRatio = img.width / img.height;
                    const isTransparent = transparencyRatio > 0.05;
                    const isPortrait = aspectRatio > 0.5 && aspectRatio < 1.1;

                    // AI Face Analysis
                    let faceValid = true;
                    let faceError = '';

                    if (modelsLoaded && typeof faceapi !== 'undefined') {
                        // Using a threshold of 0.5 to be very sure it's a human face
                        const minConfidence = 0.5;
                        const detections = await faceapi.detectAllFaces(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: minConfidence })).withFaceLandmarks();
                        
                        if (detections.length === 0) {
                            faceValid = false;
                            faceError = 'No human face detected. Please upload a clear photo of yourself.';
                        } else if (detections.length > 1) {
                            faceValid = false;
                            faceError = 'Multiple faces detected. Please ensure ONLY you are in the photo.';
                        } else {
                            // 1. Check for Front-Facing (Symmetry)
                            const landmarks = detections[0].landmarks;
                            const leftEye = landmarks.getLeftEye();
                            const rightEye = landmarks.getRightEye();
                            const nose = landmarks.getNose();
                            const eyebrowsLeft = landmarks.getLeftEyebrow();
                            const eyebrowsRight = landmarks.getRightEyebrow();

                            const noseToLeft = Math.abs(nose[0].x - leftEye[0].x);
                            const noseToRight = Math.abs(rightEye[3].x - nose[0].x);
                            const symmetryRatio = Math.min(noseToLeft, noseToRight) / Math.max(noseToLeft, noseToRight);

                            if (symmetryRatio < 0.6) {
                                faceValid = false;
                                faceError = 'Please look directly at the camera (Front View).';
                            }

                            // 2. Check for Hats/Forehead Coverage
                            // If the top of the detector box is too close to the eyebrows, it's likely a hat or hair covering the forehead.
                            const box = detections[0].detection.box;
                            const browTop = Math.min(eyebrowsLeft[0].y, eyebrowsRight[4].y);
                            const foreheadSpace = (browTop - box.y) / box.height;

                            if (foreheadSpace < 0.1) {
                                faceValid = false;
                                faceError = 'Forehead must be visible. Please remove your hat or move your hair.';
                            }

                            // 3. Simple "Obscured Eyes" check (often triggered by dark glasses)
                            // We check the average brightness of the eye area compared to the face
                            // (This is a heuristic, but helps enforce the 'No Glasses' rule)
                            if (faceValid) {
                                const leftEyeBrightness = getRegionBrightness(ctx, leftEye);
                                const rightEyeBrightness = getRegionBrightness(ctx, rightEye);
                                if (leftEyeBrightness < 30 || rightEyeBrightness < 30) {
                                    faceValid = false;
                                    faceError = 'Eyes must be clearly visible. Please remove dark glasses.';
                                }
                            }
                        }
                    }

                    resolve({ 
                        valid: isTransparent && isPortrait && faceValid, 
                        isTransparent, 
                        isPortrait, 
                        faceValid, 
                        faceError 
                    });
                    URL.revokeObjectURL(img.src);
                };
                img.onerror = () => resolve({ valid: false });
                img.src = URL.createObjectURL(file);
            });
        };

        const getRegionBrightness = (ctx, points) => {
            const xs = points.map(p => p.x);
            const ys = points.map(p => p.y);
            const minX = Math.min(...xs);
            const minY = Math.min(...ys);
            const width = Math.max(...xs) - minX;
            const height = Math.max(...ys) - minY;
            
            if (width <= 0 || height <= 0) return 255;
            
            const data = ctx.getImageData(minX, minY, width, height).data;
            let brightness = 0;
            for (let i = 0; i < data.length; i += 4) {
                brightness += (data[i] + data[i+1] + data[i+2]) / 3;
            }
            return brightness / (data.length / 4);
        };

        imageInput.addEventListener('change', async () => {
            const file = imageInput.files && imageInput.files[0] ? imageInput.files[0] : null;
            
            // 1. Initial State Handling
            if (!file) {
                if (preview) { preview.hidden = true; preview.removeAttribute('src'); }
                warning.textContent = 'Allowed image types: JPG, JPEG, PNG, WEBP. Max size: 2MB. Must be transparent background.';
                warning.className = 'warning'; // Reset classes
                refreshSubmitState();
                return;
            }

            // 2. Immediate Feedback
            warning.textContent = '⏱️ AI is analyzing your photo... Hold on.';
            warning.className = 'warning';
            if (preview) { preview.hidden = true; }
            refreshSubmitState(); // Button remains disabled while checking

            // 3. Basic File Checks (Fast)
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
            const tooLarge = file.size > (2 * 1024 * 1024);
            const badType = !allowedTypes.includes(file.type);

            if (tooLarge || badType) {
                warning.textContent = badType ? '❌ Invalid type. Use PNG or WEBP.' : '❌ Image too large (Max 2MB).';
                warning.classList.add('error-text');
                imageInput.value = '';
                refreshSubmitState();
                return;
            }

            // 4. Run AI Analysis
            const analysis = await analyzeImage(file);
            
            // 5. Final Decision Flow
            if (!analysis.valid) {
                let msg = '🚫 Validation Failed: ';
                if (!analysis.isTransparent) msg += 'Background must be transparent. ';
                else if (!analysis.isPortrait) msg += 'Must be a portrait (Passport style). ';
                else if (!analysis.faceValid) msg += analysis.faceError;
                else msg += 'Image does not meet requirements.';
                
                warning.textContent = msg;
                warning.className = 'warning error-text';
                imageInput.value = ''; // Reset input
            } else {
                warning.textContent = '✅ AI Verification Passed: This photo looks perfect!';
                warning.className = 'warning success-text';
                
                // Show Preview only on success
                if (preview) {
                    const reader = new FileReader();
                    reader.onload = (e) => { preview.src = e.target.result; preview.hidden = false; };
                    reader.readAsDataURL(file);
                }
            }
            refreshSubmitState(); // Re-check button state after analysis
        });
    }

    if (checkAll && checks.length > 0) {
        checkAll.addEventListener('change', () => {
            checks.forEach((box) => box.checked = checkAll.checked);
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
