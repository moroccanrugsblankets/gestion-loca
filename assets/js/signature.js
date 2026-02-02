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
    
    ctx = canvas.getContext('2d');
    
    // Configuration du canvas
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    
    // Fond transparent (pas de fond blanc pour éviter les bordures)
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Réinitialiser le style de dessin
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    
    // Sauvegarder l'état vide du canvas
    emptyCanvasData = canvas.toDataURL();
    
    // Événements souris
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    // Événements tactiles pour mobile
    canvas.addEventListener('touchstart', handleTouchStart);
    canvas.addEventListener('touchmove', handleTouchMove);
    canvas.addEventListener('touchend', stopDrawing);
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
    if (!ctx || !canvas) return;
    
    // Effacer complètement le canvas (transparent)
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    // Réinitialiser le style de dessin
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
}

/**
 * Obtenir les données de la signature
 */
function getSignatureData() {
    if (!canvas) return '';
    return canvas.toDataURL('image/png');
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
