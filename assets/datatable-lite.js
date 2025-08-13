// /var/www/html/calibraciones/assets/datatable-lite.js
(() => {
  function q(sel, root=document){ return root.querySelector(sel); }
  function qa(sel, root=document){ return Array.from(root.querySelectorAll(sel)); }

  // Simple sorter
  function sortTable(tbody, col, dir, type) {
    const rows = qa(':scope > tr', tbody);
    const mul = dir === 'asc' ? 1 : -1;
    const parse = (txt) => {
      if (type === 'num') return parseFloat(txt.replace(/[^\d.-]/g,'')) || 0;
      if (type === 'date') return Date.parse(txt) || 0;
      return txt.toLowerCase();
    };
    rows.sort((a,b)=>{
      const v1 = parse(a.cells[col]?.innerText.trim() || '');
      const v2 = parse(b.cells[col]?.innerText.trim() || '');
      return (v1 > v2 ? 1 : v1 < v2 ? -1 : 0) * mul;
    }).forEach(r => tbody.appendChild(r));
  }

  // Pagination
  function paginate(tableWrap) {
    const table = q('table', tableWrap);
    const tbody = table.tBodies[0];
    const allRows = qa(':scope > tr', tbody);
    const sel = q('.dt-rows-per-page', tableWrap);
    const pager = q('.dt-pager', tableWrap);
    let page = 1;
    const redraw = () => {
      const per = parseInt(sel.value,10);
      const total = Math.ceil(allRows.length / per) || 1;
      page = Math.max(1, Math.min(page, total));
      allRows.forEach((tr,i)=>{
        const show = i >= (page-1)*per && i < page*per;
        tr.style.display = show ? '' : 'none';
      });
      pager.innerHTML = `
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-sm btn-outline-secondary" data-act="first" ${page===1?'disabled':''}>&laquo;</button>
          <button class="btn btn-sm btn-outline-secondary" data-act="prev" ${page===1?'disabled':''}>&lsaquo;</button>
          <span class="small">Página ${page} / ${total}</span>
          <button class="btn btn-sm btn-outline-secondary" data-act="next" ${page===total?'disabled':''}>&rsaquo;</button>
          <button class="btn btn-sm btn-outline-secondary" data-act="last" ${page===total?'disabled':''}>&raquo;</button>
        </div>`;
      qa('button', pager).forEach(btn=>{
        btn.onclick = () => {
          const per = parseInt(sel.value,10);
          const total = Math.ceil(allRows.length / per) || 1;
          const act = btn.dataset.act;
          if (act==='first') page = 1;
          if (act==='prev') page = Math.max(1, page-1);
          if (act==='next') page = Math.min(total, page+1);
          if (act==='last') page = total;
          redraw();
        };
      });
    };
    sel.onchange = redraw;
    redraw();
    return { refresh:redraw };
  }

  // Global search
  function wireSearch(tableWrap){
    const search = q('.dt-search', tableWrap);
    const table  = q('table', tableWrap);
    const tbody  = table.tBodies[0];
    const rows   = qa(':scope > tr', tbody);
    const perSel = q('.dt-rows-per-page', tableWrap);
    const pager  = q('.dt-pager', tableWrap);

    const filter = () => {
      const qv = (search.value || '').trim().toLowerCase();
      rows.forEach(tr => {
        const text = tr.innerText.toLowerCase();
        tr.dataset._visible = (!qv || text.includes(qv)) ? '1' : '0';
      });
      // show only visible; pagination re-applied
      const visibles = rows.filter(r => r.dataset._visible!=='0');
      rows.forEach(r => r.style.display='none');
      const per = parseInt(perSel.value,10);
      const total = Math.ceil(visibles.length/per)||1;
      let page = 1;
      const draw = () => {
        visibles.forEach((tr,i)=>{
          const show = i >= (page-1)*per && i < page*per;
          tr.style.display = show ? '' : 'none';
        });
        pager.innerHTML = `
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary" data-act="first" ${page===1?'disabled':''}>&laquo;</button>
            <button class="btn btn-sm btn-outline-secondary" data-act="prev" ${page===1?'disabled':''}>&lsaquo;</button>
            <span class="small">Página ${page} / ${total}</span>
            <button class="btn btn-sm btn-outline-secondary" data-act="next" ${page===total?'disabled':''}>&rsaquo;</button>
            <button class="btn btn-sm btn-outline-secondary" data-act="last" ${page===total?'disabled':''}>&raquo;</button>
          </div>`;
        qa('button', pager).forEach(btn=>{
          btn.onclick = () => {
            if (btn.dataset.act==='first') page=1;
            if (btn.dataset.act==='prev') page=Math.max(1,page-1);
            if (btn.dataset.act==='next') page=Math.min(total,page+1);
            if (btn.dataset.act==='last') page=total;
            draw();
          };
        });
      };
      draw();
    };
    if (search) search.addEventListener('input', filter);
  }

  // Column visibility
  function wireColVis(tableWrap){
    const table = q('table', tableWrap);
    const checks = qa('.colvis-menu input[type=checkbox]', tableWrap);
    checks.forEach((chk, idx)=>{
      chk.addEventListener('change', () => {
        const colIndex = parseInt(chk.dataset.col,10);
        qa(`thead tr th:nth-child(${colIndex+1}), tbody tr td:nth-child(${colIndex+1})`, table)
          .forEach(cell => cell.style.display = chk.checked ? '' : 'none');
      });
      // init (checked)
      const colIndex = parseInt(chk.dataset.col,10);
      if (!chk.checked) {
        qa(`thead tr th:nth-child(${colIndex+1}), tbody tr td:nth-child(${colIndex+1})`, table)
          .forEach(cell => cell.style.display = 'none');
      }
    });
  }

  // Density switch
  function wireDensity(container){
    const toggle = q('.dt-density', container);
    const root   = container.closest('.table-density-comfort, .table-density-compact') || container;
    if (!toggle) return;
    toggle.addEventListener('change', () => {
      if (toggle.value === 'compact') {
        root.classList.add('table-density-compact');
        root.classList.remove('table-density-comfort');
      } else {
        root.classList.add('table-density-comfort');
        root.classList.remove('table-density-compact');
      }
    });
  }

  // Per-column filters (select[data-col])
  function wireColFilters(tableWrap){
    const table = q('table', tableWrap);
    const tbody = table.tBodies[0];
    const rows  = qa(':scope > tr', tbody);
    const selects = qa('.dt-filter[data-col]');
    const doFilter = () => {
      const active = selects.map(sel => [parseInt(sel.dataset.col,10), sel.value]);
      rows.forEach(tr => {
        let visible = true;
        active.forEach(([i,val])=>{
          if (!val) return;
          const cellTxt = (tr.cells[i]?.innerText || '').trim().toLowerCase();
          if (!cellTxt.includes(val.toLowerCase())) visible = false;
        });
        tr.style.display = visible ? '' : 'none';
      });
    };
    selects.forEach(sel => sel.addEventListener('change', doFilter));
  }

  // INIT on pages
  window.addEventListener('DOMContentLoaded', () => {
    qa('.dt-container').forEach(container => {
      // Sorting
      qa('thead th.th-sort', container).forEach((th, idx) => {
        th.addEventListener('click', () => {
          const table = th.closest('table');
          const tbody = table.tBodies[0];
          const type = th.dataset.sort || 'text';
          const cur  = th.dataset.dir === 'asc' ? 'desc' : 'asc';
          qa('thead th.th-sort', container).forEach(t => t.classList.remove('active'));
          th.classList.add('active'); th.dataset.dir = cur;
          const ind = th.querySelector('.sort-ind');
          if (ind) ind.textContent = cur === 'asc' ? '▲' : '▼';
          sortTable(tbody, idx, cur, type);
        });
      });

      // Pagination & search
      const pager = paginate(container);
      wireSearch(container);

      // Column visibility, density, filters
      wireColVis(container);
      wireDensity(container);
      wireColFilters(container);
    });
  });
})();
