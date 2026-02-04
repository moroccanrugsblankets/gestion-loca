/**
 * Gestion de la signature électronique sur canvas
 * My Invest Immobilier
 */

// Configuration constants
const JPEG_QUALITY = 0.95; // Quality for JPEG compression (0-1)

let canvas;
let ctx;
let isDrawing = false;
let lastX = 0;
let lastY = 0;
let emptyCanvasData = '';
let tempCanvas; // Temporary canvas for JPEG conversion with white background
let tempCtx;

/**
 * Initialiser le canvas de signature
 */
function initSignature() {
    canvas = document.getElementById('signatureCanvas');
    if (!canvas) {
        console.error('Canvas non trouvé');
        return;
    }
    
    console.log('Initialisation du canvas de signature');
    console.log('- Dimensions:', canvas.width, 'x', canvas.height, 'px');
    
    ctx = canvas.getContext('2d');
    
    // Configuration du canvas
    // IMPORTANT: No borders should be added to the canvas to avoid borders in the saved JPEG
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    
    // Fond transparent pour le dessin (sera converti avec fond blanc lors de la sauvegarde JPEG)
    // Clear canvas for drawing with transparent background
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    console.log('- Fond: transparent pour le dessin (converti en blanc lors de la sauvegarde JPEG)');
    console.log('- Style de trait: noir (#000000), largeur 2px');
    
    // Réinitialiser le style de dessin
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    
    // Créer un canvas temporaire réutilisable pour la conversion JPEG avec fond blanc
    // Check if tempCanvas already exists to avoid memory leaks on reinitialization
    if (!tempCanvas) {
        tempCanvas = document.createElement('canvas');
        tempCanvas.width = canvas.width;
        tempCanvas.height = canvas.height;
        tempCtx = tempCanvas.getContext('2d');
        console.log('- Canvas temporaire créé pour conversion JPEG');
    } else {
        // Reuse existing canvas but update dimensions if needed
        if (tempCanvas.width !== canvas.width || tempCanvas.height !== canvas.height) {
            tempCanvas.width = canvas.width;
            tempCanvas.height = canvas.height;
            console.log('- Canvas temporaire redimensionné');
        } else {
            console.log('- Canvas temporaire réutilisé');
        }
    }
    
    // Sauvegarder l'état vide du canvas avec fond blanc pour JPEG
    tempCtx.fillStyle = '#FFFFFF';
    tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
    tempCtx.drawImage(canvas, 0, 0);
    emptyCanvasData = tempCanvas.toDataURL('image/jpeg', JPEG_QUALITY);
    console.log('- Canvas vide capturé avec fond blanc (taille:', emptyCanvasData.length, 'bytes)');
    
    // Événements souris
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    // Événements tactiles pour mobile
    canvas.addEventListener('touchstart', handleTouchStart);
    canvas.addEventListener('touchmove', handleTouchMove);
    canvas.addEventListener('touchend', stopDrawing);
    
    console.log('✓ Canvas de signature initialisé avec succès');
}

/**
 * Obtenir la position de la souris relative au canvas
 */
function getMousePos(e) {
    const rect = canvas.getBoundingClientRect();
    return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
    };
}

/**
 * Commencer à dessiner
 */
function startDrawing(e) {
    isDrawing = true;
    const pos = getMousePos(e);
    lastX = pos.x;
    lastY = pos.y;
}

/**
 * Dessiner
 */
function draw(e) {
    if (!isDrawing) return;
    
    e.preventDefault();
    
    const pos = getMousePos(e);
    
    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    
    lastX = pos.x;
    lastY = pos.y;
}

/**
 * Arrêter de dessiner
 */
function stopDrawing() {
    isDrawing = false;
}

/**
 * Gérer le début du toucher (mobile)
 */
function handleTouchStart(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousedown', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    canvas.dispatchEvent(mouseEvent);
}

/**
 * Gérer le mouvement du toucher (mobile)
 */
function handleTouchMove(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousemove', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    canvas.dispatchEvent(mouseEvent);
}

/**
 * Effacer la signature
 */
function clearSignature() {
    if (!ctx || !canvas) {
        console.warn('Cannot clear signature: canvas or context not initialized');
        return;
    }
    
    // Effacer complètement le canvas (transparent)
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Réinitialiser le style de dessin
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    
    console.log('Signature effacée (canvas transparent)');
}

/**
 * Obtenir les données de la signature
 */
function getSignatureData() {
    if (!canvas) {
        console.error('Canvas not found when getting signature data');
        return '';
    }
    
    if (!tempCanvas || !tempCtx) {
        console.error('Temporary canvas not initialized');
        return '';
    }
    
    // Reuse the temporary canvas with white background for JPEG conversion
    // Fill with white background (JPEG doesn't support transparency)
    tempCtx.fillStyle = '#FFFFFF';
    tempCtx.fillRect(0, 0, tempCanvas.width, tempCanvas.height);
    
    // Draw the signature on top of the white background
    tempCtx.drawImage(canvas, 0, 0);
    
    const signatureData = tempCanvas.toDataURL('image/jpeg', JPEG_QUALITY);
    console.log('Signature captured:');
    console.log('- Data URI length:', signatureData.length, 'bytes');
    console.log('- Canvas dimensions:', canvas.width, 'x', canvas.height, 'px');
    console.log('- Data URI preview:', signatureData.substring(0, 60) + '...');
    
    return signatureData;
}

/**
 * Obtenir les données du canvas vide (pour comparaison)
 */
function getEmptyCanvasData() {
    return emptyCanvasData;
}

/**
 * Vérifier si le canvas est vide
 */
function isCanvasEmpty() {
    return getSignatureData() === emptyCanvasData;
}
