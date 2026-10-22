// ══════════════════════════════════════════════
//  STATE
// ══════════════════════════════════════════════
let selections = {
    timeFood: [],
    consistency: [],
    specifications: [],
};

const inputMap = {
    timeFood: "f-tiempo",
    consistency: "f-consist",
    specifications: "f-espec",
};

// ══════════════════════════════════════════════
//  MODAL OPEN/CLOSE
// ══════════════════════════════════════════════

function openEditModal(id, diet) {
    editingId = id;

    // console.log("Diet recibida:", diet);

    // Abrir modal
    document.getElementById("modalEditOverlay" + id).classList.add("open");

    // Rellenar inputs del formulario
    document.getElementById("edit-paciente-" + id).value =
        diet.name_patient ?? "";
    document.getElementById("edit-habitacion-" + id).value =
        diet.habitation ?? "";
    document.getElementById("edit-observaciones-" + id).value =
        diet.current_version.observations ?? "";
    document.getElementById("edit-changes-" + id).value =
        diet.current_version.changes ?? "NA";
    // Tipo de dieta para facturación ("" si la versión no lo tiene)
    const billing = document.getElementById("edit-billing-type-" + id);
    if (billing) billing.value = diet.current_version.billing_type ?? "";

    // Valores del enum: "Sí" | "No" (los antiguos "Si" se muestran como "Sí")
    document.getElementById("edit-isolation-" + id).value =
        diet.current_version.isolation === "No" || !diet.current_version.isolation ? "No" : "Sí";

    selections["edit-timeFood-" + id] = diet.current_version.timeFood ?? [];
    renderChips("cs-timeFood-" + id, "edit-timeFood-" + id);

    selections["edit-consistency-" + id] =
        diet.current_version.consistency ?? [];
    renderChips("cs-consistency-" + id, "edit-consistency-" + id);

    selections["edit-specifications-" + id] =
        diet.current_version.specifications ?? [];
    renderChips("cs-specifications-" + id, "edit-specifications-" + id);
}
function renderChips(csId, key) {
    // ============================
    // 1. Detectar contenedores (crear o editar)
    // ============================
    const baseKey = key.replace("edit-", "");
    const chipContainer =
        document.getElementById("chips-edit-" + baseKey) ||
        document.getElementById("chips-" + baseKey);

    const hiddenContainer =
        document.getElementById("hidden-edit-" + baseKey) ||
        document.getElementById("hidden-" + baseKey);

    let opts = document.querySelectorAll(`#opts-edit-${baseKey} .chip-option`);
    if (opts.length === 0) {
        opts = document.querySelectorAll(`#opts-${baseKey} .chip-option`);
    }
    console.log(chipContainer);
    // Seguridad: si no existe el contenedor, no seguir
    if (!chipContainer || !hiddenContainer) {
        console.warn("No se encontró el contenedor para key:", key);
        return;
    }

    // ============================
    // 2. Renderizar chips
    // ============================
    const colorKey = baseKey.split("-")[0];

    const LABEL_MAP =
        colorKey === "specifications"
            ? SPEC_LABELS
            : colorKey === "timeFood"
              ? TIME_LABELS
              : colorKey === "consistency"
                ? CONS_LABELS
                : {};

    chipContainer.innerHTML = selections[key]
        .map(
            (v) => `
        <span class="chip"
            style="background-color: ${
                colorKey === "timeFood"
                    ? "var(--dieta-chip-tiempo)"
                    : colorKey === "consistency"
                      ? "var(--dieta-chip-consistencia)"
                      : "var(--dieta-chip-especificacion)"
            };
            color: ${
                colorKey === "timeFood"
                    ? "var(--dieta-chip-tiempo-texto)"
                    : "var(--dieta-chip-texto)"
            };">
            ${LABEL_MAP[v] ?? v}
            <span class="remove"
                onclick="removeChip('${key}','${v}', '${csId}'); event.stopPropagation()">×</span>
        </span>
    `,
        )
        .join("");

    // ============================
    // 3. Marcar opciones seleccionadas
    // ============================
    opts.forEach((o) => {
        o.classList.toggle("selected", selections[key].includes(o.dataset.val));
    });

    // ============================
    // 4. Crear inputs hidden
    // ============================
    hiddenContainer.innerHTML = "";
    selections[key].forEach((v) => {
        hiddenContainer.innerHTML += `<input type="hidden" name="${baseKey.split("-")[0]}[]" value="${v}">`;
    });
}

function openCreateModal() {
    editingId = null;
    // resetForm();
    document.getElementById("modalTitle").textContent = "Registrar Dieta";
    document.getElementById("saveBtnText").textContent = "Registrar";
    document.getElementById("modalOverlay").classList.add("open");
}

function closeModal() {
    document
        .querySelectorAll(".overlay.open")
        .forEach((el) => el.classList.remove("open"));
    editingId = null;
}

// ══════════════════════════════════════════════
//  CHIP SELECT
// ══════════════════════════════════════════════

// Abre/cierra el selector
function toggleChipSelect(id) {
    const el = document.getElementById(id);
    const was = el.classList.contains("open");
    document
        .querySelectorAll(".chip-select.open")
        .forEach((e) => e.classList.remove("open"));
    if (!was) el.classList.add("open");
}

// Cierra si se hace clic fuera
document.addEventListener("click", (e) => {
    if (!e.target.closest(".chip-select")) {
        document
            .querySelectorAll(".chip-select.open")
            .forEach((el) => el.classList.remove("open"));
    }
});

// Seleccionar/deseleccionar chip
function selectChip(e, csId, key, act) {
    e.stopPropagation();
    const val = e.target.dataset.val;

    selections[key] = selections[key].includes(val)
        ? selections[key].filter((v) => v !== val)
        : [...selections[key], val];

    renderChips(csId, key);
}

// Eliminar chip desde la X
function removeChip(key, val, csId) {
    selections[key] = selections[key].filter((v) => v !== val);
    renderChips(csId, key);
}

// ══════════════════════════════════════════════
//  VALIDACIÓN AL ENVIAR (registrar y editar)
//  El servidor valida lo mismo (StoreDietRequest); esto solo avisa antes de enviar.
// ══════════════════════════════════════════════
const REQUIRED_CHIPS = {
    timeFood: "Elige al menos un tiempo de comida.",
    consistency: "Elige al menos una consistencia.",
    specifications: "Elige al menos una especificación.",
};

// Igual que App\Rules\NoHtml: etiquetas, comentarios o instrucciones («<b>», «</p>», «<!--»)
const HTML_PATTERN = /<\s*[\/!?a-z]/i;

function showFieldError(anchor, message) {
    let error = anchor.nextElementSibling;
    if (!error || !error.classList.contains("field-error")) {
        error = document.createElement("p");
        error.className = "field-error";
        error.setAttribute("role", "alert");
        anchor.after(error);
    }
    error.textContent = message;
}

function clearFieldErrors(form) {
    form.querySelectorAll(".field-error").forEach((el) => el.remove());
}

document.addEventListener("submit", (e) => {
    const form = e.target;
    const hiddenLists = form.querySelectorAll('[id^="hidden-"]');
    if (hiddenLists.length === 0) return; // no es un formulario de dieta

    clearFieldErrors(form);
    let first = null;

    hiddenLists.forEach((hidden) => {
        const key = hidden.id.replace(/^hidden-(edit-)?/, "").split("-")[0];
        if (REQUIRED_CHIPS[key] && !hidden.querySelector("input")) {
            const select = hidden.previousElementSibling ?? hidden;
            showFieldError(select, REQUIRED_CHIPS[key]);
            first ??= select;
        }
    });

    form.querySelectorAll('input[type="text"], textarea').forEach((field) => {
        if (HTML_PATTERN.test(field.value)) {
            showFieldError(field, "No se permite código HTML (etiquetas como <b> o <script>).");
            first ??= field;
        }
    });

    if (first) {
        e.preventDefault();
        first.scrollIntoView({ block: "center", behavior: "smooth" });
    }
});
