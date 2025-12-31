// ============================================================================
// SISTEMA DE ALERTAS PERSONALIZADAS
// Reemplaza alert() y confirm() con modales estilizados
// ============================================================================

// Crear contenedor de toasts si no existe
if (!document.getElementById('toastContainer')) {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
}

// ============================================================================
// MODAL DE ALERTA (reemplaza alert())
// ============================================================================
function showAlert(message, type = 'info', title = null) {
    return new Promise((resolve) => {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        const titles = {
            success: 'Éxito',
            error: 'Error',
            warning: 'Advertencia',
            info: 'Información'
        };

        const overlay = document.createElement('div');
        overlay.className = 'custom-modal-overlay';
        overlay.innerHTML = `
            <div class="custom-modal">
                <div class="custom-modal-header">
                    <div class="custom-modal-icon ${type}">${icons[type]}</div>
                    <h3 class="custom-modal-title">${title || titles[type]}</h3>
                </div>
                <div class="custom-modal-body">
                    <p class="custom-modal-message">${message}</p>
                </div>
                <div class="custom-modal-footer">
                    <button class="custom-modal-btn custom-modal-btn-primary" id="alertOkBtn">Aceptar</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        // Mostrar con animación
        requestAnimationFrame(() => {
            overlay.classList.add('show');
        });

        // Cerrar al hacer clic en Aceptar
        const okBtn = overlay.querySelector('#alertOkBtn');
        okBtn.focus();
        
        const closeModal = () => {
            overlay.classList.remove('show');
            setTimeout(() => {
                overlay.remove();
                resolve();
            }, 300);
        };

        okBtn.addEventListener('click', closeModal);
        
        // Cerrar con Escape o clic fuera
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal();
        });
        
        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape' || e.key === 'Enter') {
                document.removeEventListener('keydown', handler);
                closeModal();
            }
        });
    });
}

// ============================================================================
// MODAL DE CONFIRMACIÓN (reemplaza confirm())
// ============================================================================
function showConfirm(message, title = 'Confirmar acción', options = {}) {
    return new Promise((resolve) => {
        const {
            confirmText = 'Confirmar',
            cancelText = 'Cancelar',
            type = 'confirm',
            confirmClass = 'primary'
        } = options;

        const overlay = document.createElement('div');
        overlay.className = 'custom-modal-overlay';
        overlay.innerHTML = `
            <div class="custom-modal">
                <div class="custom-modal-header">
                    <div class="custom-modal-icon ${type}">❓</div>
                    <h3 class="custom-modal-title">${title}</h3>
                </div>
                <div class="custom-modal-body">
                    <p class="custom-modal-message">${message}</p>
                </div>
                <div class="custom-modal-footer">
                    <button class="custom-modal-btn custom-modal-btn-secondary" id="confirmCancelBtn">${cancelText}</button>
                    <button class="custom-modal-btn custom-modal-btn-${confirmClass}" id="confirmOkBtn">${confirmText}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);

        requestAnimationFrame(() => {
            overlay.classList.add('show');
        });

        const okBtn = overlay.querySelector('#confirmOkBtn');
        const cancelBtn = overlay.querySelector('#confirmCancelBtn');
        okBtn.focus();

        const closeModal = (result) => {
            overlay.classList.remove('show');
            setTimeout(() => {
                overlay.remove();
                resolve(result);
            }, 300);
        };

        okBtn.addEventListener('click', () => closeModal(true));
        cancelBtn.addEventListener('click', () => closeModal(false));
        
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) closeModal(false);
        });

        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape') {
                document.removeEventListener('keydown', handler);
                closeModal(false);
            } else if (e.key === 'Enter') {
                document.removeEventListener('keydown', handler);
                closeModal(true);
            }
        });
    });
}

// ============================================================================
// TOAST NOTIFICATIONS (notificaciones pequeñas)
// ============================================================================
function showToast(message, type = 'info', duration = 4000, title = null) {
    const container = document.getElementById('toastContainer');
    
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };

    const titles = {
        success: 'Éxito',
        error: 'Error',
        warning: 'Advertencia',
        info: 'Información'
    };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type]}</span>
        <div class="toast-content">
            <div class="toast-title">${title || titles[type]}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close">×</button>
    `;

    container.appendChild(toast);

    // Mostrar con animación
    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    // Auto cerrar
    const autoClose = setTimeout(() => {
        closeToast(toast);
    }, duration);

    // Cerrar al hacer clic
    toast.querySelector('.toast-close').addEventListener('click', () => {
        clearTimeout(autoClose);
        closeToast(toast);
    });
}

function closeToast(toast) {
    toast.classList.remove('show');
    setTimeout(() => {
        toast.remove();
    }, 300);
}

// ============================================================================
// MODAL DE CARGA (loading)
// ============================================================================
let loadingModal = null;

function showLoading(message = 'Procesando...') {
    if (loadingModal) return;

    loadingModal = document.createElement('div');
    loadingModal.className = 'custom-modal-overlay';
    loadingModal.innerHTML = `
        <div class="custom-modal" style="text-align: center; padding: 2rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem; animation: spin 1s linear infinite;">⏳</div>
            <p class="custom-modal-message" style="margin: 0; font-weight: 500;">${message}</p>
        </div>
    `;

    document.body.appendChild(loadingModal);

    requestAnimationFrame(() => {
        loadingModal.classList.add('show');
    });
}

function hideLoading() {
    if (!loadingModal) return;

    loadingModal.classList.remove('show');
    setTimeout(() => {
        loadingModal.remove();
        loadingModal = null;
    }, 300);
}

// ============================================================================
// Estilos de animación adicionales
// ============================================================================
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);

