/**
 * Gestion de la signature électronique sur canvas
 * My Invest Immobilier
 */

let canvas;
let ctx;
let isDrawing = false;
let lastX = 0;
let lastY = 0;
let emptyCanvasData = '';

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
    
    // Fond blanc (clearRect puis conversion JPEG donnera un fond blanc)
    // This ensures the saved JPEG will have a clean white background with no borders
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    console.log('- Fond: blanc (clearRect appliqué, converti en blanc par JPEG)');
    console.log('- Style de trait: noir (#000000), largeur 2px');
    
    // Réinitialiser le style de dessin
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    
    // Sauvegarder l'état vide du canvas
    emptyCanvasData = canvas.toDataURL('image/jpeg', 0.95);
    console.log('- Canvas vide capturé (taille:', emptyCanvasData.length, 'bytes)');
    
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
    
    const signatureData = canvas.toDataURL('image/jpeg', 0.95);
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
