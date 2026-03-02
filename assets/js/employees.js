// employees.js — COMPLETO + modal + upload foto com crop/resize
// + FIX definitivo: nunca setar value em input type=file (photo)
(() => {
  const api = 'api.php';
  const CSRF_TOKEN = (typeof window !== 'undefined' && window.CSRF_TOKEN) ? String(window.CSRF_TOKEN) : '';

  function withCsrfFormData(fd) {
    if (CSRF_TOKEN && !fd.has('_csrf')) fd.append('_csrf', CSRF_TOKEN);
    return fd;
  }

  function withCsrfParams(params) {
    if (CSRF_TOKEN && !params.has('_csrf')) params.set('_csrf', CSRF_TOKEN);
    return params;
  }

  // ------------------
  // ELEMENTOS
  // ------------------
  const listBody = document.getElementById('list-body');
  if (!listBody) return;

  const q = document.getElementById('q');
  const status = document.getElementById('status');
  const dept = document.getElementById('dept');
  const btnSearch = document.getElementById('btn-search');
  const btnClear = document.getElementById('btn-clear');
  const btnNew = document.getElementById('btn-new');
  const btnExport = document.getElementById('btn-export');
  const importFile = document.getElementById('import-file');
  const pager = document.getElementById('pager');
  const chkAll = document.getElementById('chk-all');
  const bulkActivate = document.getElementById('bulk-activate');
  const bulkDeactivate = document.getElementById('bulk-deactivate');
  const bulkDelete = document.getElementById('bulk-delete');
  const toast = document.getElementById('toast');
  const modalRoot = document.getElementById('modal-root');

  let page = 1;
  let debounceTimer = null;

  // foto processada (crop/resize) pra enviar no submit
  let processedPhotoFile = null;

  // ------------------
  // TOAST
  // ------------------
  function toastMsg(t) {
    if (!toast) return alert(t);
    toast.textContent = t;
    toast.style.display = "block";
    setTimeout(() => { toast.style.display = "none"; }, 3000);
  }

  // ------------------
  // ESCAPE HTML
  // ------------------
  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
  }

  // ------------------
  // CROP/RESIZE FOTO (quadrado central)
  // ------------------
  async function cropAndResizeToSquare(file, size = 256, quality = 0.9) {
    const img = await fileToImage(file);
    const sw = img.naturalWidth || img.width;
    const sh = img.naturalHeight || img.height;

    const s = Math.min(sw, sh);
    const sx = Math.floor((sw - s) / 2);
    const sy = Math.floor((sh - s) / 2);

    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;

    const ctx = canvas.getContext('2d', { alpha: true });
    ctx.clearRect(0, 0, size, size);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(img, sx, sy, s, s, 0, 0, size, size);

    const blob = await canvasToBlob(canvas, 'image/webp', quality).catch(() =>
      canvasToBlob(canvas, 'image/jpeg', quality)
    );

    const ext = blob.type === 'image/webp' ? 'webp' : 'jpg';
    const base = (file.name || 'photo').replace(/\.[^.]+$/, '');
    return new File([blob], `${base}_${size}x${size}.${ext}`, { type: blob.type });
  }

  function fileToImage(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Imagem inválida')); };
      img.src = url;
    });
  }

  function canvasToBlob(canvas, type, quality) {
    return new Promise((resolve, reject) => {
      canvas.toBlob((b) => {
        if (!b) return reject(new Error('Falha ao gerar imagem'));
        resolve(b);
      }, type, quality);
    });
  }

  // ------------------
  // MODAL
  // ------------------
  function closeModal() {
    if (modalRoot) modalRoot.innerHTML = '';
    document.body.style.overflow = '';
    processedPhotoFile = null;
  }

  function openEditModal(id = null) {
    if (!modalRoot) return alert("Erro: #modal-root não existe no HTML.");

    processedPhotoFile = null;
    document.body.style.overflow = 'hidden';
    const title = id ? 'Editar Funcionário' : 'Novo Funcionário';

    modalRoot.innerHTML = `
      <div class="modal-bg fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-3 z-50" role="dialog" aria-modal="true">
        <div class="modal-box w-full max-w-3xl rounded-2xl border border-gray-800 bg-gray-900 shadow-xl">
          <div class="flex items-center justify-between gap-3 p-4 border-b border-gray-800">
            <div>
              <div class="text-lg font-semibold">${escapeHtml(title)}</div>
              <div class="text-sm text-gray-400">Preencha os dados e clique em salvar.</div>
            </div>
            <button type="button" class="btn-secondary px-3 py-2 rounded-xl" data-close>Fechar</button>
          </div>

          <div class="p-4">
            <form id="emp-form" class="grid grid-cols-1 md:grid-cols-2 gap-3" enctype="multipart/form-data">
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="id" value="${id ?? ''}">

              <div class="md:col-span-2 flex items-center gap-4">
                <div class="w-16 h-16 rounded-full overflow-hidden bg-gray-800 border border-gray-700" id="photo-preview">
                  <img src="/assets/img/avatar-placeholder.png" class="w-full h-full object-cover" style="object-position:center;">
                </div>
                <div class="flex-1">
                  <label class="text-sm text-gray-300">Foto (opcional)</label>
                  <input id="photo-input" type="file" name="photo" accept="image/*" class="input mt-1">
                  <div class="text-xs text-gray-500 mt-1">A imagem será ajustada automaticamente (quadrada) para o ícone.</div>
                </div>
              </div>

              <div>
                <label class="text-sm text-gray-300">Nome</label>
                <input name="name" class="input mt-1" required>
              </div>

              <div>
                <label class="text-sm text-gray-300">Matrícula</label>
                <input name="registration" class="input mt-1">
              </div>

              <div>
                <label class="text-sm text-gray-300">CPF</label>
                <input name="cpf" class="input mt-1" placeholder="000.000.000-00">
              </div>

              <div>
                <label class="text-sm text-gray-300">Status</label>
                <select name="status" class="input mt-1">
                  <option value="ATIVO">ATIVO</option>
                  <option value="FERIAS">FÉRIAS</option>
                  <option value="AFASTADO">AFASTADO</option>
                  <option value="INATIVO">INATIVO</option>
                </select>
              </div>

              <div>
                <label class="text-sm text-gray-300">E-mail</label>
                <input name="email" class="input mt-1" type="email">
              </div>

              <div>
                <label class="text-sm text-gray-300">Telefone</label>
                <input name="phone" class="input mt-1">
              </div>

              <div>
                <label class="text-sm text-gray-300">Departamento</label>
                <select name="department_id" id="department_id" class="input mt-1">
                  <option value="">Carregando...</option>
                </select>
              </div>

              <div>
                <label class="text-sm text-gray-300">Cargo</label>
                <select name="position_id" id="position_id" class="input mt-1">
                  <option value="">Carregando...</option>
                </select>
              </div>

              <div class="md:col-span-2">
                <label class="text-sm text-gray-300">Observações</label>
                <textarea name="notes" class="input mt-1" rows="3"></textarea>
              </div>

              <div class="md:col-span-2 flex items-center justify-end gap-2 pt-2">
                <button type="button" class="btn-secondary px-4 py-2 rounded-xl" data-cancel>Cancelar</button>
                <button type="submit" class="btn-primary px-4 py-2 rounded-xl">Salvar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    `;

    const bg = modalRoot.querySelector('.modal-bg');
    modalRoot.querySelectorAll('[data-close],[data-cancel]').forEach(b => b.addEventListener('click', closeModal));
    bg.addEventListener('click', (e) => { if (e.target === bg) closeModal(); });

    const form = modalRoot.querySelector('#emp-form');
    const depSel = modalRoot.querySelector('#department_id');
    const posSel = modalRoot.querySelector('#position_id');
    const photoInput = modalRoot.querySelector('#photo-input');
    const photoPreview = modalRoot.querySelector('#photo-preview');

    // options
    fetch(`${api}?action=options`)
      .then(r => r.json())
      .then(d => {
        if (!d.ok) return;
        depSel.innerHTML = '<option value="">Selecione...</option>' +
          (d.departments || []).map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
        posSel.innerHTML = '<option value="">Selecione...</option>' +
          (d.positions || []).map(x => `<option value="${x.id}">${escapeHtml(x.name)}</option>`).join('');
      })
      .catch(() => {
        depSel.innerHTML = '<option value="">Erro ao carregar</option>';
        posSel.innerHTML = '<option value="">Erro ao carregar</option>';
      });

    // processa e faz preview da foto escolhida
    photoInput.addEventListener('change', async () => {
      const file = photoInput.files && photoInput.files[0];
      if (!file) { processedPhotoFile = null; return; }

      try {
        processedPhotoFile = await cropAndResizeToSquare(file, 256, 0.9);
        const url = URL.createObjectURL(processedPhotoFile);
        photoPreview.innerHTML = `<img src="${url}" class="w-full h-full object-cover" style="object-position:center;">`;
        setTimeout(() => URL.revokeObjectURL(url), 1500);
      } catch (err) {
        processedPhotoFile = null;
        alert('Erro ao ajustar imagem: ' + (err.message || err));
      }
    });

    // carrega dados se edição
    if (id) {
      fetch(`${api}?action=get&id=${encodeURIComponent(id)}`)
        .then(r => r.json())
        .then(d => {
          if (!d.ok) return alert(d.error || 'Erro ao carregar');
          const data = d.data || {};

          // FIX DEFINITIVO: nunca setar value em file input (nem por nome nem por type)
          Object.entries(data).forEach(([k, v]) => {
            // pula foto por chave
            if (k === 'photo') return;

            let el = null;
            try {
              el = form.querySelector(`[name="${CSS.escape(k)}"]`);
            } catch {
              el = form.querySelector(`[name="${k}"]`);
            }
            if (!el) return;

            // pula qualquer input file
            if (el instanceof HTMLInputElement && el.type === 'file') {
              try { el.value = ''; } catch {}
              return;
            }

            if (v === null || v === undefined) return;

            // seta com proteção
            try { el.value = String(v); } catch {}
          });

          // preview da foto existente (sem mexer no input file)
          if (data.photo) {
            photoPreview.innerHTML =
              `<img src="${escapeHtml(data.photo)}" class="w-full h-full object-cover" style="object-position:center;">`;
          }

          // garante selects no valor correto
          setTimeout(() => {
            if (data.department_id != null) depSel.value = String(data.department_id);
            if (data.position_id != null) posSel.value = String(data.position_id);
          }, 200);
        })
        .catch(err => alert('Erro: ' + err.message));
    }

    // submit (substitui foto pelo arquivo processado)
    form.addEventListener('submit', (e) => {
      e.preventDefault();

      const fd = new FormData(form);
      if (processedPhotoFile) {
        fd.delete('photo');
        fd.append('photo', processedPhotoFile, processedPhotoFile.name);
      }
      withCsrfFormData(fd);

      fetch(api, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
          if (!d.ok) return alert(d.error || 'Erro ao salvar');
          toastMsg('Salvo com sucesso!');
          closeModal();
          load();
        })
        .catch(err => alert('Erro: ' + err.message));
    });

    // ESC fecha
    window.addEventListener('keydown', function onEsc(ev) {
      if (ev.key === 'Escape') {
        window.removeEventListener('keydown', onEsc);
        closeModal();
      }
    });
  }

  // ------------------
  // LISTAGEM
  // ------------------
  function renderRows(rows) {
    if (!rows || !rows.length) {
      listBody.innerHTML =
        '<tr><td colspan="9" class="p-6 text-center text-gray-400">Nenhum funcionário encontrado.</td></tr>';
      return;
    }

    listBody.innerHTML = rows.map(r => `
      <tr data-id="${r.id}">
        <td class="px-4"><input class="chk" type="checkbox" value="${r.id}"></td>
        <td class="px-4">
          <img src="${r.photo || '/assets/img/avatar-placeholder.png'}"
               class="avatar"
               style="width:42px;height:42px;border-radius:9999px;object-fit:cover;object-position:center;"
               onerror="this.onerror=null;this.src='/assets/img/avatar-placeholder.png';">
        </td>
        <td class="px-4 font-mono text-sm">${escapeHtml(r.registration || '—')}</td>
        <td class="px-4 font-medium">${escapeHtml(r.name)}</td>
        <td class="px-4 text-sm">${escapeHtml(r.email || '—')}<br>${escapeHtml(r.phone || '—')}</td>
        <td class="px-4 text-sm">${escapeHtml(r.dept || '—')}</td>
        <td class="px-4 text-sm">${escapeHtml(r.pos || '—')}</td>
        <td class="px-4">
          <span class="badge ${r.status === 'ATIVO' ? 'green' : (r.status === 'FERIAS' ? 'orange' : 'red')}">
            ${escapeHtml(r.status)}
          </span>
        </td>
        <td class="px-4 text-right">
          <button class="btn-edit btn-primary px-2 py-1 rounded" data-id="${r.id}">Editar</button>
          <button class="btn-delete btn-danger px-2 py-1 rounded" data-id="${r.id}">Excluir</button>
        </td>
      </tr>
    `).join('');

    document.querySelectorAll('.btn-edit').forEach(b =>
      b.addEventListener('click', () => openEditModal(parseInt(b.dataset.id, 10)))
    );

    document.querySelectorAll('.btn-delete').forEach(b =>
      b.addEventListener('click', () => {
        if (!confirm("Excluir registro?")) return;

        const body = new URLSearchParams();
        body.set('action', 'delete');
        body.set('id', b.dataset.id);
        withCsrfParams(body);

        fetch(api, {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: body.toString()
        })
          .then(r => r.json())
          .then(d => {
            if (d.ok) { toastMsg("Excluído"); load(); }
            else alert(d.error || "Erro");
          })
          .catch(e => alert("Erro: " + e.message));
      })
    );
  }

  // ------------------
  // PAGINADOR
  // ------------------
  function renderPager(total, pageNow, perPage) {
    if (!pager) return;

    const totalPages = Math.max(1, Math.ceil((total || 0) / (perPage || 20)));
    if (totalPages <= 1) { pager.innerHTML = ''; return; }

    const windowSize = 7;
    let start = Math.max(1, pageNow - Math.floor(windowSize / 2));
    let end = Math.min(totalPages, start + windowSize - 1);
    start = Math.max(1, end - windowSize + 1);

    let html = '';
    if (pageNow > 1) html += `<button class="btn" data-page="${pageNow - 1}">‹</button> `;

    for (let i = start; i <= end; i++) {
      html += `<button class="btn ${i === pageNow ? 'bg-orange-500 text-white' : ''}" data-page="${i}">${i}</button> `;
    }

    if (pageNow < totalPages) html += `<button class="btn" data-page="${pageNow + 1}">›</button>`;

    pager.innerHTML = html;

    pager.querySelectorAll("button[data-page]").forEach(b =>
      b.addEventListener("click", () => {
        page = Number(b.dataset.page);
        load();
      })
    );
  }

  // ------------------
  // CARREGAR LISTA
  // ------------------
  function load() {
    const qs = new URLSearchParams({
      q: q?.value || '',
      status: status?.value || '',
      department: dept?.value || '',
      page: String(page)
    });

    listBody.innerHTML = `<tr><td colspan="9" class="p-6 text-center text-gray-400">Carregando...</td></tr>`;

    fetch(`${api}?action=list&${qs.toString()}`)
      .then(r => r.json())
      .then(d => {
        if (!d.ok) return alert(d.error || "Erro");
        renderRows(d.data || []);
        renderPager(d.total || 0, d.page || page, d.perPage || 20);
        if (chkAll) chkAll.checked = false;
      })
      .catch(e => {
        listBody.innerHTML = `<tr><td colspan="9" class="p-6 text-center text-red-400">Erro: ${escapeHtml(e.message)}</td></tr>`;
      });
  }

  // ------------------
  // EVENTOS
  // ------------------
  if (btnSearch) btnSearch.addEventListener("click", () => { page = 1; load(); });

  if (btnClear) btnClear.addEventListener("click", () => {
    if (q) q.value = '';
    if (status) status.value = '';
    if (dept) dept.value = '';
    page = 1;
    load();
  });

  if (q) q.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => { page = 1; load(); }, 350);
  });

  if (status) status.addEventListener("change", () => { page = 1; load(); });
  if (dept) dept.addEventListener("change", () => { page = 1; load(); });

  if (btnNew) btnNew.addEventListener("click", () => openEditModal(null));

  if (btnExport) btnExport.addEventListener("click", () => {
    const qs = new URLSearchParams({
      q: q?.value || '',
      status: status?.value || '',
      department: dept?.value || ''
    });
    window.location = `${api}?action=export&${qs.toString()}`;
  });

  if (importFile) importFile.addEventListener("change", () => {
    const f = importFile.files && importFile.files[0];
    if (!f) return;
    if (!confirm("Confirmar importação do CSV?")) return;

    const fd = new FormData();
    fd.append("action", "import");
    fd.append("file", f);
    withCsrfFormData(fd);

    fetch(api, { method: "POST", body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.ok) { toastMsg(`Importados: ${d.imported}`); load(); }
        else alert(d.error || "Erro");
      })
      .catch(e => alert("Erro: " + e.message))
      .finally(() => { try { importFile.value = ''; } catch {} });
  });

  if (chkAll) chkAll.addEventListener("change", () => {
    document.querySelectorAll('.chk').forEach(c => (c.checked = chkAll.checked));
  });

  function getSelectedIds() {
    return Array.from(document.querySelectorAll('.chk:checked')).map(c => c.value);
  }

  function bulk(op, confirmMsg, successMsg) {
    const ids = getSelectedIds();
    if (!ids.length) return alert("Nenhum selecionado");
    if (!confirm(confirmMsg)) return;

    const body = new URLSearchParams();
    body.set('action', 'bulk');
    body.set('op', op);
    ids.forEach(id => body.append('ids[]', id));
    withCsrfParams(body);

    fetch(api, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString()
    })
      .then(r => r.json())
      .then(d => {
        if (d.ok) { toastMsg(successMsg); load(); }
        else alert(d.error || "Erro");
      })
      .catch(e => alert("Erro: " + e.message));
  }

  if (bulkActivate) bulkActivate.addEventListener("click", () =>
    bulk("activate", "Ativar selecionados?", "Ativados")
  );
  if (bulkDeactivate) bulkDeactivate.addEventListener("click", () =>
    bulk("deactivate", "Inativar selecionados?", "Inativados")
  );
  if (bulkDelete) bulkDelete.addEventListener("click", () =>
    bulk("delete", "Excluir definitivamente?", "Excluídos")
  );

  // ------------------
  // INICIAL
  // ------------------
  load();
})();
