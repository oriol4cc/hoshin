const API = './api.php';

const headerRow = document.getElementById('headerRow');
const body = document.getElementById('boardBody');
const addRootObjectiveBtn = document.getElementById('addRootObjectiveBtn');

const editor = document.getElementById('cardEditor');
const editorTitle = document.getElementById('editorTitle');
const fieldTitle = document.getElementById('fieldTitle');
const fieldDescription = document.getElementById('fieldDescription');
const fieldCode = document.getElementById('fieldCode');
const fieldLinked = document.getElementById('fieldLinked');
const codeRow = document.getElementById('codeRow');
const linkedRow = document.getElementById('linkedRow');
const saveBtn = document.getElementById('saveCardBtn');
const cancelBtn = document.getElementById('cancelCardBtn');

let board = { columns: [], objectives: [], cards: [], yearly_codes: [], yearly_column_id: null };
let editCtx = null;

async function request(action, method = 'GET', data = null) {
  const res = await fetch(`${API}?action=${encodeURIComponent(action)}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: data ? JSON.stringify(data) : undefined,
  });
  const payload = await res.json();
  if (!payload.ok) throw new Error(payload.error || 'Error API');
  return payload.data || payload;
}

function sortCols() {
  return [...board.columns].sort((a, b) => a.position - b.position);
}

function flattenObjectives() {
  const map = new Map();
  board.objectives.forEach((o) => {
    const key = o.parent_id ?? 'root';
    if (!map.has(key)) map.set(key, []);
    map.get(key).push(o);
  });

  const result = [];
  function walk(parent, level) {
    const children = (map.get(parent) || []).sort((a, b) => a.position - b.position);
    children.forEach((c) => {
      result.push({ ...c, level });
      walk(c.id, level + 1);
    });
  }
  walk('root', 0);
  return result;
}

function cardsIn(objectiveId, columnId) {
  return board.cards
    .filter((c) => c.objective_id === objectiveId && c.column_id === columnId)
    .sort((a, b) => a.position - b.position);
}

function render() {
  renderHeader();
  body.innerHTML = '';

  flattenObjectives().forEach((objective) => {
    const tr = document.createElement('tr');

    const titleTd = document.createElement('td');
    titleTd.className = 'objective-cell';
    titleTd.style.paddingLeft = `${12 + objective.level * 20}px`;

    titleTd.innerHTML = `
      <div class="objective-title">${escapeHtml(objective.title)}</div>
      <div class="objective-actions">
        <button data-a="child">+Sub</button>
        <button data-a="up">↑</button>
        <button data-a="down">↓</button>
        <button data-a="del">✕</button>
      </div>
    `;

    titleTd.querySelector('[data-a="child"]').onclick = async () => {
      const t = prompt('Títol subobjectiu');
      if (!t) return;
      await request('objective', 'POST', { title: t, parent_id: objective.id });
      await refresh();
    };
    titleTd.querySelector('[data-a="up"]').onclick = async () => {
      await request('objective-move', 'PUT', { id: objective.id, direction: 'up' });
      await refresh();
    };
    titleTd.querySelector('[data-a="down"]').onclick = async () => {
      await request('objective-move', 'PUT', { id: objective.id, direction: 'down' });
      await refresh();
    };
    titleTd.querySelector('[data-a="del"]').onclick = async () => {
      if (!confirm('Eliminar objectiu i descendència?')) return;
      await request('objective', 'DELETE', { id: objective.id });
      await refresh();
    };

    tr.appendChild(titleTd);

    sortCols().forEach((col) => {
      const td = document.createElement('td');
      td.className = 'cell';
      td.dataset.objectiveId = objective.id;
      td.dataset.columnId = col.id;

      const add = document.createElement('button');
      add.className = 'add-card';
      add.textContent = '+ Card';
      add.onclick = () => openEditor({ mode: 'create', objectiveId: objective.id, columnId: col.id });
      td.appendChild(add);

      cardsIn(objective.id, col.id).forEach((card) => td.appendChild(renderCard(card)));

      td.addEventListener('dragover', (e) => e.preventDefault());
      td.addEventListener('drop', async (e) => {
        e.preventDefault();
        const id = Number(e.dataTransfer.getData('cardId'));
        if (!id) return;
        const pos = td.querySelectorAll('.card').length + 1;
        try {
          await request('card-move', 'PUT', {
            id,
            objective_id: Number(td.dataset.objectiveId),
            column_id: Number(td.dataset.columnId),
            position: pos,
          });
          await refresh();
        } catch (err) {
          alert(err.message);
        }
      });

      tr.appendChild(td);
    });

    body.appendChild(tr);
  });
}

function renderHeader() {
  headerRow.innerHTML = '<th class="obj-col">Objectius</th>';
  sortCols().forEach((c) => {
    const th = document.createElement('th');
    th.textContent = c.title;
    headerRow.appendChild(th);
  });
}

function renderCard(card) {
  const el = document.createElement('article');
  el.className = 'card';
  el.draggable = true;
  el.addEventListener('dragstart', (e) => e.dataTransfer.setData('cardId', String(card.id)));

  const metaCode = card.code ? `<small>Codi: ${escapeHtml(card.code)}</small>` : '';
  const metaLinked = card.linked_yearly_code ? `<small>Vinculat: ${escapeHtml(card.linked_yearly_code)}</small>` : '';

  el.innerHTML = `
    <div class="card-main">
      <strong>${escapeHtml(card.title)}</strong>
      ${metaCode}
      ${metaLinked}
    </div>
    <div class="card-actions">
      <button data-a="edit">✎</button>
      <button data-a="del">✕</button>
    </div>
  `;

  el.querySelector('[data-a="edit"]').onclick = () => openEditor({ mode: 'edit', card });
  el.querySelector('[data-a="del"]').onclick = async () => {
    await request('card', 'DELETE', { id: card.id });
    await refresh();
  };

  return el;
}

function openEditor(ctx) {
  editCtx = ctx;
  editor.classList.remove('hidden');

  if (ctx.mode === 'create') {
    editorTitle.textContent = 'Nova card';
    fieldTitle.value = '';
    fieldDescription.value = '';
    fieldCode.value = '';
    populateLinked('');
    setupEditorByColumn(ctx.columnId);
  } else {
    editorTitle.textContent = 'Editar card';
    fieldTitle.value = ctx.card.title || '';
    fieldDescription.value = ctx.card.description || '';
    fieldCode.value = ctx.card.code || '';
    populateLinked(ctx.card.linked_yearly_code || '');
    setupEditorByColumn(ctx.card.column_id);
  }
}

function setupEditorByColumn(columnId) {
  const isYearly = Number(columnId) === Number(board.yearly_column_id);
  codeRow.style.display = isYearly ? 'block' : 'none';
  linkedRow.style.display = isYearly ? 'none' : 'block';

  fieldCode.required = isYearly;
  fieldLinked.required = !isYearly;
}

function populateLinked(selected) {
  fieldLinked.innerHTML = '<option value="">Selecciona...</option>';
  board.yearly_codes.forEach((code) => {
    const o = document.createElement('option');
    o.value = code;
    o.textContent = code;
    o.selected = code === selected;
    fieldLinked.appendChild(o);
  });
}

saveBtn.addEventListener('click', async () => {
  if (!editCtx) return;

  const title = fieldTitle.value.trim();
  if (!title) return alert('Títol obligatori');

  const payload = {
    title,
    description: fieldDescription.value.trim(),
    code: fieldCode.value.trim() || null,
    linked_yearly_code: fieldLinked.value || null,
  };

  try {
    if (editCtx.mode === 'create') {
      await request('card', 'POST', {
        ...payload,
        objective_id: editCtx.objectiveId,
        column_id: editCtx.columnId,
      });
    } else {
      await request('card', 'PUT', {
        id: editCtx.card.id,
        ...payload,
      });
    }

    closeEditor();
    await refresh();
  } catch (err) {
    alert(err.message);
  }
});

cancelBtn.addEventListener('click', closeEditor);

function closeEditor() {
  editCtx = null;
  editor.classList.add('hidden');
}

addRootObjectiveBtn.addEventListener('click', async () => {
  const t = prompt('Títol objectiu estratègic');
  if (!t) return;
  await request('objective', 'POST', { title: t });
  await refresh();
});

async function refresh() {
  board = await request('board', 'GET');
  render();
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.innerText = str ?? '';
  return div.innerHTML;
}

refresh().catch((e) => {
  console.error(e);
  alert(e.message);
});
