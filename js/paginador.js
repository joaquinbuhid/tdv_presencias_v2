// Paginador simple para los listados del backoffice.
// Uso: renderPaginador(elemento, {page, pages, total, per_page}, (nuevaPagina) => cargar(nuevaPagina));
(function () {
    if (!document.getElementById('paginador-css')) {
        const style = document.createElement('style');
        style.id = 'paginador-css';
        style.textContent = `
            .paginador { display:flex; justify-content:space-between; align-items:center; gap:.6rem; flex-wrap:wrap; margin-top:.8rem; font-size:.85rem; color:var(--text-muted); }
            .paginador-botones { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; }
            .paginador-botones .btn { width:auto; }
            .paginador-botones .btn[disabled] { opacity:.45; cursor:default; }
        `;
        document.head.appendChild(style);
    }
})();

function renderPaginador(el, info, onPage) {
    if (!el) return;
    const total = Number(info.total) || 0;
    const page = Number(info.page) || 1;
    const pages = Math.max(1, Number(info.pages) || 1);
    const perPage = Number(info.per_page) || total || 1;
    if (!total) {
        el.innerHTML = '';
        return;
    }
    const desde = (page - 1) * perPage + 1;
    const hasta = Math.min(total, page * perPage);
    const btn = (p, texto, deshabilitado, titulo) =>
        `<button type="button" class="btn btn-outline btn-sm" data-p="${p}" title="${titulo}" ${deshabilitado ? 'disabled' : ''}>${texto}</button>`;

    el.classList.add('paginador');
    el.innerHTML = `
        <span>Mostrando ${desde}–${hasta} de ${total}</span>
        ${pages > 1 ? `<span class="paginador-botones">
            ${btn(1, '«', page <= 1, 'Primera página')}
            ${btn(page - 1, '‹ Anterior', page <= 1, 'Página anterior')}
            <span>Página ${page} de ${pages}</span>
            ${btn(page + 1, 'Siguiente ›', page >= pages, 'Página siguiente')}
            ${btn(pages, '»', page >= pages, 'Última página')}
        </span>` : ''}`;
    el.querySelectorAll('button[data-p]').forEach((b) => {
        b.addEventListener('click', () => onPage(Number(b.dataset.p)));
    });
}
