// ============================================================================
// UTILIDADES
// ============================================================================

const formatearFecha = (fecha) => {
    if (!fecha) return 'N/A';
    // Asume formato YYYY-MM-DD
    const [año, mes, dia] = fecha.split('-');
    return `${dia}/${mes}/${año}`;
};

const generarContenidoPDF = (registro) => {
    // Nota: El código se ha simplificado para mejorar la legibilidad y sigue la estructura solicitada.
    return `
╔═══════════════════════════════════════════════════════════════════╗
║          DEPARTAMENTO ADMINISTRATIVO DE HACIENDA                  ║
║           CONSULTA DE ACTOS ADMINISTRATIVOS                       ║
╚═══════════════════════════════════════════════════════════════════╝

───────────────────────────────────────────────────────────────────
INFORMACIÓN DEL CONTRIBUYENTE
───────────────────────────────────────────────────────────────────
ID Principal:               ${registro.id}
ID Alterno (Predio):        ${registro.idAlterno || 'N/A'}
Razón Social:               ${registro.razonSocial}

───────────────────────────────────────────────────────────────────
INFORMACIÓN DEL ACTO ADMINISTRATIVO
───────────────────────────────────────────────────────────────────
No. Acto:                   ${registro.noActoAdministrativo}
Tipo de Actuación:          ${registro.tipoActuacion}
Fecha de Acto:              ${formatearFecha(registro.fechaActo)}
Fecha de Publicación:       ${formatearFecha(registro.fechaPublicacion)}
Fecha de Desfijación:       ${formatearFecha(registro.fechaDesfijacion)}

───────────────────────────────────────────────────────────────────
INFORMACIÓN ADMINISTRATIVA
───────────────────────────────────────────────────────────────────
Organismo:                  ${registro.organismo}
Área:                       ${registro.area}
Estado:                     ${registro.estado === 'tramite' ? 'EN TRÁMITE' : 'FINALIZADO'}

───────────────────────────────────────────────────────────────────
Documento generado el: ${new Date().toLocaleString('es-CO')}
───────────────────────────────────────────────────────────────────
`.trim();
};

const descargarPDF = (registro) => {
    if (!registro) return;
    const contenido = generarContenidoPDF(registro);
    const blob = new Blob([contenido], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `ActoAdministrativo_${registro.noActoAdministrativo}.txt`;
    a.click();
    URL.revokeObjectURL(url);
};

// ============================================================================
// ESTADO DE LA APLICACIÓN Y REFERENCIAS DEL DOM
// ============================================================================

let resultados = [];
let pestanaActiva = 'tramite';
let isLoading = false;
let error = null;
let registroSeleccionado = null;
let hasBuscado = false; // Para saber si ya se hizo una búsqueda

// Filtros del historial
let filtroAnio = '';
let filtroDependencia = '';

// Referencias del DOM
const idBusquedaInput = document.getElementById('id-busqueda');
const btnBuscar = document.getElementById('btn-buscar');
const errorAlertDiv = document.getElementById('error-alert');
const resultsContainer = document.getElementById('results-container');
const resultsContent = document.getElementById('results-content');
const tabTramite = document.getElementById('tab-tramite');
const tabHistorial = document.getElementById('tab-historial');
const tramiteCountSpan = document.getElementById('tramite-count');
const historialCountSpan = document.getElementById('historial-count');
const detailModal = document.getElementById('detail-modal');
const modalContent = document.getElementById('modal-content');

// ============================================================================
// COMPONENTES DOM (RENDERIZACIÓN DE HTML)
// ============================================================================

const LoadingSpinner = () => `
    <div class="spinner-container">
        <div class="spinner-icon">🔄</div>
        <p class="spinner-text">Consultando base de datos...</p>
    </div>
`;

const ErrorAlert = (message) => `
    <div class="error-alert">
        <span class="icon-alert-circle">⚠️</span>
        <div class="flex-1">
            <p class="error-alert-message">${message}</p>
        </div>
        <button id="close-error" class="close-button-error">✖</button>
    </div>
`;

const EmptyState = (pestana) => `
    <div class="empty-state">
        <div class="empty-state-icon">${pestana === 'tramite' ? '📄' : '🕒'}</div>
        <h3 class="empty-state-title">Sin resultados</h3>
        <p class="empty-state-message">
            No se encontraron actos administrativos ${pestana === 'tramite' ? 'en trámite' : 'históricos'} para el ID consultado.
        </p>
    </div>
`;

const DetailItem = (title, value, span = 1, highlight = false) => `
    <div class="detail-item ${highlight ? 'highlight' : 'base'} ${span > 1 ? `col-span-${span}` : ''}">
        <p class="detail-label">${title}</p>
        <p class="detail-value ${highlight ? 'highlight-value' : ''}">${value}</p>
    </div>
`;

const ResultCard = (registro) => {
    const isTramite = registro.estado === 'tramite';
    const estadoClass = isTramite ? 'tag-tramite' : 'tag-finalizado';
    const noActo = registro.noActoAdministrativo;
    
    // Verificar si hay datos extra
    let tieneInfoExtra = false;
    if (registro.masDatos) {
        try {
            const datos = typeof registro.masDatos === 'string' 
                ? JSON.parse(registro.masDatos) 
                : registro.masDatos;
            tieneInfoExtra = datos && Object.keys(datos).length > 0;
        } catch (e) {
            tieneInfoExtra = false;
        }
    }
    
    return `
        <div class="result-card">
            <div class="result-header">
                <div class="flex-1">
                    <span class="tag-acto-numero">${noActo}</span>
                    <h3 class="result-title">${registro.razonSocial}</h3>
                </div>
                <span class="tag-estado ${estadoClass}">
                    ${isTramite ? 'EN TRÁMITE' : 'FINALIZADO'}
                </span>
            </div>
            
            <p class="result-type">${registro.tipoActuacion}</p>
            
            <div class="result-grid">
                <div>
                    <p class="result-label">ID</p>
                    <p class="result-value">${registro.id}</p>
                </div>
                <div>
                    <p class="result-label">Publicación</p>
                    <p class="result-value">${formatearFecha(registro.fechaPublicacion)}</p>
                </div>
                <div class="col-span-2">
                    <p class="result-label">Área</p>
                    <p class="result-value-small">${registro.area}</p>
                </div>
            </div>
            
            <div class="result-actions">
                ${tieneInfoExtra ? `
                    <button class="action-button action-info-extra" data-id="${noActo}" style="background: #6366f1; flex: 0 0 auto;">
                        <span>📋</span>
                        Info Extra
                    </button>
                ` : ''}
                ${isTramite ? `
                    <button class="action-button action-view" data-id="${noActo}">
                        <span class="icon-eye">👁️</span>
                        Ver Detalle
                    </button>
                ` : ''}
                <button class="action-button action-download ${isTramite ? 'flex-1' : 'w-full'}" data-id="${noActo}">
                    <span class="icon-download">⬇️</span>
                    Descargar
                </button>
            </div>
        </div>
    `;
};

// Obtener años únicos de los registros
const obtenerAniosUnicos = (registros) => {
    const anios = registros.map(r => new Date(r.fechaPublicacion).getFullYear());
    return [...new Set(anios)].sort((a, b) => b - a);
};

// Obtener dependencias únicas de los registros
const obtenerDependenciasUnicas = (registros) => {
    const dependencias = registros.map(r => r.area);
    return [...new Set(dependencias)].sort();
};

// Filtros del historial (temporales antes de aplicar)
let filtroAnioTemp = '';
let filtroDependenciaTemp = '';

// Filtros del historial
const HistorialFilters = (registros) => {
    const anios = obtenerAniosUnicos(registros);
    const dependencias = obtenerDependenciasUnicas(registros);
    
    return `
        <div class="historial-filters">
            <div class="filter-group">
                <label class="filter-label">Año</label>
                <select id="filtro-anio" class="filter-select">
                    <option value="">Todos</option>
                    ${anios.map(a => `<option value="${a}" ${filtroAnioTemp == a ? 'selected' : ''}>${a}</option>`).join('')}
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Dependencia</label>
                <select id="filtro-dependencia" class="filter-select">
                    <option value="">Todas</option>
                    ${dependencias.map(d => `<option value="${d}" ${filtroDependenciaTemp === d ? 'selected' : ''}>${d.length > 50 ? d.substring(0, 50) + '...' : d}</option>`).join('')}
                </select>
            </div>
            <div class="filter-buttons">
                <button id="btn-aplicar-filtros" class="btn-aplicar-filtros">🔍 Aplicar</button>
                <button id="btn-limpiar-filtros" class="btn-limpiar-filtros">🗑️ Limpiar</button>
            </div>
        </div>
    `;
};

// Tabla para el historial
const HistorialTable = (registros, registrosFiltrados) => {
    if (registrosFiltrados.length === 0) {
        return `
            ${HistorialFilters(registros)}
            <div class="empty-state" style="padding: 2rem;">
                <div class="empty-state-icon">🔍</div>
                <h3 class="empty-state-title">Sin resultados</h3>
                <p class="empty-state-message">No hay registros que coincidan con los filtros seleccionados.</p>
            </div>
        `;
    }

    const filas = registrosFiltrados.map(r => `
        <tr>
            <td class="table-cell">${r.noActoAdministrativo}</td>
            <td class="table-cell">${r.razonSocial}</td>
            <td class="table-cell table-cell-hide-sm">${formatearFecha(r.fechaPublicacion)}</td>
            <td class="table-cell table-cell-hide-md">${r.tipoActuacion.substring(0, 40)}...</td>
            <td class="table-cell table-actions">
                <button class="btn-table-action action-view" data-id="${r.noActoAdministrativo}" title="Ver detalle">👁️</button>
                <button class="btn-table-action action-download" data-id="${r.noActoAdministrativo}" title="Descargar">⬇️</button>
            </td>
        </tr>
    `).join('');

    return `
        ${HistorialFilters(registros)}
        <div class="table-info">Mostrando ${registrosFiltrados.length} de ${registros.length} registros</div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="table-header">No. Acto</th>
                        <th class="table-header">Razón Social</th>
                        <th class="table-header table-cell-hide-sm">Fecha</th>
                        <th class="table-header table-cell-hide-md">Tipo Actuación</th>
                        <th class="table-header">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    ${filas}
                </tbody>
            </table>
        </div>
    `;
};

const DetailModalContent = (registro) => {
    if (!registro) return '';
    
    return `
        <div class="modal-header print-hidden">
            <h2 class="modal-title">
                <span class="icon-file-text">📄</span>
                Detalle del Acto Administrativo
            </h2>
            <button id="close-modal" class="close-modal-button">✖</button>
        </div>
        
        <div class="modal-body">
            <div class="print-only">
                <h1 class="print-title">DEPARTAMENTO ADMINISTRATIVO DE HACIENDA</h1>
                <h2 class="print-subtitle">Detalle del Acto Administrativo</h2>
            </div>
            
            <div class="modal-sections">
                <div class="modal-section">
                    <h3 class="section-title">Información del Contribuyente</h3>
                    <div class="section-grid grid-3">
                        ${DetailItem("Razón Social", registro.razonSocial, 3, true)}
                        ${DetailItem("ID Principal", registro.id)}
                        ${DetailItem("ID Alterno (Predio)", registro.idAlterno || 'N/A', 2)}
                    </div>
                </div>

                <div class="modal-section">
                    <h3 class="section-title">Información del Acto</h3>
                    <div class="section-grid grid-2">
                        ${DetailItem("No. Acto Administrativo", registro.noActoAdministrativo, 2, true)}
                        ${DetailItem("Tipo de Actuación", registro.tipoActuacion, 2)}
                    </div>
                </div>

                <div class="modal-section">
                    <h3 class="section-title">Fechas Relevantes</h3>
                    <div class="section-grid grid-3">
                        ${DetailItem("Fecha de Acto", formatearFecha(registro.fechaActo))}
                        ${DetailItem("Fecha de Publicación", formatearFecha(registro.fechaPublicacion))}
                        ${DetailItem("Fecha de Desfijación", formatearFecha(registro.fechaDesfijacion))}
                    </div>
                </div>
                
                <div class="modal-section-last">
                    <h3 class="section-title">Información Administrativa</h3>
                    <div class="section-grid grid-2">
                        ${DetailItem("Organismo", registro.organismo)}
                        ${DetailItem("Área Competente", registro.area)}
                        ${DetailItem(
                            "Estado Actual", 
                            registro.estado === 'tramite' ? 'EN TRÁMITE' : 'FINALIZADO', 
                            2, 
                            true
                        )}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="modal-footer print-hidden">
            <button id="btn-modal-close" class="modal-button-secondary">Cerrar</button>
            <button id="btn-modal-print" class="modal-button-blue">
                <span class="icon-printer">🖨️</span>
                Imprimir
            </button>
            <button id="btn-modal-download" class="modal-button-primary" data-id="${registro.noActoAdministrativo}">
                <span class="icon-download">⬇️</span>
                Descargar
            </button>
        </div>
    `;
};

// ============================================================================
// LÓGICA DE ESTADO Y DATOS
// ============================================================================

const setResultados = (data) => {
    resultados = data;
};

const setError = (message) => {
    error = message;
};

const setIsLoading = (loading) => {
    isLoading = loading;
    const isReady = !loading && idBusquedaInput.value.trim();
    
    idBusquedaInput.disabled = loading;
    btnBuscar.disabled = loading || !idBusquedaInput.value.trim();
    
    document.getElementById('search-icon').innerHTML = loading ? '🔄' : '🔍';
    document.getElementById('search-text').textContent = loading ? 'Buscando...' : 'Buscar';
    btnBuscar.classList.toggle('search-button-loading', loading);
};

const setPestanaActiva = (pestana) => {
    pestanaActiva = pestana;
    tabTramite.classList.toggle('tab-active', pestana === 'tramite');
    tabHistorial.classList.toggle('tab-active', pestana === 'historial');
    tabTramite.querySelector('.tab-count').classList.toggle('tab-inactive-count', pestana !== 'tramite');
    tabHistorial.querySelector('.tab-count').classList.toggle('tab-inactive-count', pestana !== 'historial');
    renderResults();
};

const buscarEnBD = async () => {
    const idTrimmed = idBusquedaInput.value.trim();
    
    if (!idTrimmed) {
        setError('Por favor ingrese un ID válido para realizar la búsqueda.');
        renderApp();
        return;
    }

    setError(null);
    setIsLoading(true);
    registroSeleccionado = null;
    hasBuscado = true;
    renderApp();

    try {
        // Llama a la API PHP con el ID de búsqueda
        const response = await fetch(`api.php?id=${encodeURIComponent(idTrimmed)}`);
        const data = await response.json();
        
        if (response.status !== 200) {
            // Manejo de errores 400 o 500 desde la API PHP
            setError(data.error || 'Error desconocido al procesar la solicitud.');
            setResultados([]);
        } else {
            setResultados(data);
            if (data.length === 0) {
                 // Si la API devuelve un array vacío, mostramos el mensaje de error para el usuario
                setError(`No se encontraron actos administrativos para el ID: ${idTrimmed}`);
            }
        }
    } catch (e) {
        console.error('Error en la búsqueda:', e);
        setError('Error de conexión de red o del servidor. Por favor, intente nuevamente.');
        setResultados([]);
    } finally {
        setIsLoading(false);
        renderApp();
    }
};

const verDetalle = (noActo) => {
    registroSeleccionado = resultados.find(r => r.noActoAdministrativo === noActo);
    if (registroSeleccionado) {
        detailModal.classList.remove('modal-overlay-hidden');
        detailModal.classList.add('modal-overlay');
        modalContent.innerHTML = DetailModalContent(registroSeleccionado);
        
        // Asignar listeners al modal
        document.getElementById('close-modal').onclick = cerrarModal;
        document.getElementById('btn-modal-close').onclick = cerrarModal;
        document.getElementById('btn-modal-print').onclick = () => window.print();
        
        // Listener de descarga dentro del modal
        const btnModalDownload = document.getElementById('btn-modal-download');
        if(btnModalDownload) {
            btnModalDownload.onclick = () => descargarPDF(registroSeleccionado);
        }
    }
};

const cerrarModal = () => {
    registroSeleccionado = null;
    detailModal.classList.remove('modal-overlay');
    detailModal.classList.add('modal-overlay-hidden');
    modalContent.innerHTML = '';
};

// ============================================================================
// FUNCIONES DE RENDERIZADO
// ============================================================================

const renderError = () => {
    if (error && !isLoading) {
        errorAlertDiv.innerHTML = ErrorAlert(error);
        errorAlertDiv.classList.remove('error-alert-hidden');
        errorAlertDiv.querySelector('#close-error').onclick = () => {
            setError(null);
            renderError();
        };
    } else {
        errorAlertDiv.classList.add('error-alert-hidden');
        errorAlertDiv.innerHTML = '';
    }
};

// Aplicar filtros del historial
const aplicarFiltrosHistorial = (registros) => {
    return registros.filter(r => {
        const anioRegistro = new Date(r.fechaPublicacion).getFullYear();
        const cumpleAnio = !filtroAnio || anioRegistro == filtroAnio;
        const cumpleDependencia = !filtroDependencia || r.area === filtroDependencia;
        return cumpleAnio && cumpleDependencia;
    });
};

const renderResults = () => {
    const resultadosPorEstado = resultados.filter(r => 
        r.estado === (pestanaActiva === 'tramite' ? 'tramite' : 'finalizado')
    );

    // Actualizar contadores
    const tramiteCount = resultados.filter(r => r.estado === 'tramite').length;
    const historialCount = resultados.filter(r => r.estado === 'finalizado').length;
    tramiteCountSpan.textContent = tramiteCount;
    historialCountSpan.textContent = historialCount;
    
    // Estado inicial - no se ha buscado aún
    if (!hasBuscado && resultados.length === 0) {
        resultsContent.innerHTML = `
            <div class="empty-results">
                <div class="empty-results-icon">🔍</div>
                <div class="empty-results-title">Ingrese un ID para consultar</div>
                <div class="empty-results-text">
                    Puede buscar por número de cédula o número de predio para ver los actos administrativos asociados.
                </div>
            </div>
        `;
        return;
    }
    
    if (isLoading) {
        resultsContent.innerHTML = LoadingSpinner();
    } else if (resultados.length === 0 && error) {
        resultsContent.innerHTML = EmptyState(pestanaActiva);
    } else if (resultadosPorEstado.length === 0) {
        resultsContent.innerHTML = EmptyState(pestanaActiva);
    } else {
        // Renderizar según la pestaña activa
        if (pestanaActiva === 'historial') {
            // Aplicar filtros adicionales al histórico
            const historialFiltrado = aplicarFiltrosHistorial(resultadosPorEstado);
            resultsContent.innerHTML = HistorialTable(resultadosPorEstado, historialFiltrado);
            
            // Asignar eventos a los filtros
            const selectAnio = document.getElementById('filtro-anio');
            const selectDependencia = document.getElementById('filtro-dependencia');
            const btnAplicar = document.getElementById('btn-aplicar-filtros');
            const btnLimpiar = document.getElementById('btn-limpiar-filtros');
            
            // Los selectores solo guardan valores temporales (sin re-render)
            if (selectAnio) {
                selectAnio.onchange = (e) => {
                    filtroAnioTemp = e.target.value;
                };
            }
            if (selectDependencia) {
                selectDependencia.onchange = (e) => {
                    filtroDependenciaTemp = e.target.value;
                };
            }
            
            // Botón APLICAR: aplica los filtros y re-renderiza
            if (btnAplicar) {
                btnAplicar.onclick = () => {
                    filtroAnio = filtroAnioTemp;
                    filtroDependencia = filtroDependenciaTemp;
                    renderResults();
                };
            }
            
            // Botón LIMPIAR: resetea todo
            if (btnLimpiar) {
                btnLimpiar.onclick = () => {
                    filtroAnio = '';
                    filtroDependencia = '';
                    filtroAnioTemp = '';
                    filtroDependenciaTemp = '';
                    renderResults();
                };
            }
        } else {
            // Usar tarjetas para trámite
            resultsContent.innerHTML = `
                <div class="results-grid">
                    ${resultadosPorEstado.map((registro) => ResultCard(registro)).join('')}
                </div>
            `;
        }

        // Asignar eventos dinámicos (botones de acción)
        resultsContent.querySelectorAll('.action-view').forEach(button => {
            const noActo = button.dataset.id;
            button.onclick = (e) => {
                e.preventDefault();
                verDetalle(noActo);
            };
        });
        
        resultsContent.querySelectorAll('.action-download').forEach(button => {
            const noActo = button.dataset.id;
            const registro = resultados.find(r => r.noActoAdministrativo === noActo);
            button.onclick = (e) => {
                e.preventDefault();
                descargarPDF(registro);
            };
        });
        
        // Evento para botón de Info Extra
        resultsContent.querySelectorAll('.action-info-extra').forEach(button => {
            const noActo = button.dataset.id;
            const registro = resultados.find(r => r.noActoAdministrativo === noActo);
            button.onclick = (e) => {
                e.preventDefault();
                mostrarInfoExtra(registro);
            };
        });
    }
};

// ============================================================================
// MOSTRAR INFO EXTRA (columnas adicionales del Excel)
// ============================================================================
const mostrarInfoExtra = (registro) => {
    if (!registro || !registro.masDatos) {
        alert('No hay información adicional disponible');
        return;
    }
    
    let datos;
    try {
        datos = typeof registro.masDatos === 'string' 
            ? JSON.parse(registro.masDatos) 
            : registro.masDatos;
    } catch (e) {
        alert('Error al leer la información adicional');
        return;
    }
    
    if (!datos || Object.keys(datos).length === 0) {
        alert('No hay información adicional disponible');
        return;
    }
    
    // Crear contenido del modal
    let contenidoHtml = `
        <div style="max-height: 400px; overflow-y: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f1f5f9;">
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #e2e8f0; font-size: 0.85rem;">Campo</th>
                        <th style="padding: 0.75rem; text-align: left; border-bottom: 2px solid #e2e8f0; font-size: 0.85rem;">Valor</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    for (const [clave, valor] of Object.entries(datos)) {
        contenidoHtml += `
            <tr>
                <td style="padding: 0.6rem 0.75rem; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #475569; font-size: 0.85rem;">
                    ${clave}
                </td>
                <td style="padding: 0.6rem 0.75rem; border-bottom: 1px solid #e2e8f0; color: #1f2937; font-size: 0.85rem;">
                    ${valor || '-'}
                </td>
            </tr>
        `;
    }
    
    contenidoHtml += `
                </tbody>
            </table>
        </div>
    `;
    
    // Crear modal
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay';
    overlay.id = 'info-extra-modal';
    overlay.innerHTML = `
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header" style="background: #6366f1;">
                <h2 class="modal-title">
                    <span>📋</span> Información Adicional
                </h2>
                <button class="close-modal-button" onclick="cerrarInfoExtra()">×</button>
            </div>
            <div class="modal-body" style="padding: 0;">
                <div style="padding: 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                    <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                        <strong>${registro.noActoAdministrativo}</strong> - ${registro.razonSocial}
                    </p>
                </div>
                ${contenidoHtml}
            </div>
            <div class="modal-footer">
                <button class="action-button action-download" style="flex: 1;" onclick="cerrarInfoExtra()">
                    Cerrar
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
};

const cerrarInfoExtra = () => {
    const modal = document.getElementById('info-extra-modal');
    if (modal) {
        modal.remove();
    }
};

const renderApp = () => {
    renderError();
    renderResults();
};


// ============================================================================
// INICIALIZACIÓN Y EVENT LISTENERS PRINCIPALES
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    renderApp(); 

    // Eventos de la barra de búsqueda
    btnBuscar.onclick = buscarEnBD;
    idBusquedaInput.oninput = () => {
        setIsLoading(isLoading); // Esto recalcula si el botón Buscar debe estar disabled
    };
    idBusquedaInput.onkeypress = (e) => {
        if (e.key === 'Enter' && idBusquedaInput.value.trim() && !isLoading) {
            buscarEnBD();
        }
    };

    // Eventos de las pestañas
    tabTramite.onclick = () => setPestanaActiva('tramite');
    tabHistorial.onclick = () => setPestanaActiva('historial');
});