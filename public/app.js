const API = './api.php';

const headerRow = document.getElementById('headerRow');
const boardBody = document.getElementById('boardBody');
const addRootObjectiveBtn = document.getElementById('addRootObjectiveBtn');
const controlsTpl = document.getElementById('objectiveControlsTpl');

const cardDialog = document.getElementById('cardDialog');
const cardForm = document.getElementById('cardForm');
const cardDialogTitle = document.getElementById('cardDialogTitle');
const cardTitle = document.getElementById('cardTitle');
const cardDescription = document.getElementById('cardDescription');
const cardCode = document.getElementById('cardCode');
const cardLinkedCode = document.getElementById('cardLinkedCode');
const yearlyCodeRow = document.getElementById('yearlyCodeRow');
const linkedCodeRow = document.getElementById('linkedCodeRow');

let board = { columns: [], objectives: [], cards: [], yearly_codes: [] };
let currentCardContext = null;

async function api(action, method = 'GET', body = null) {
  const res = await fetch(`${API}?action=${encodeURIComponent(action)}`, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json();
  if (!data.ok) throw new Error(data.error || 'API error');
  return data;
}

function sortedColumns() {
  return [...board.columns].sort((a, b) => a.position - b.position);
}

function getYearlyColumnId() {
  const first = sortedColumns()[0];
  return first ? Number(first.id) : null;
}

function groupObjectives(objectives) {
  const byParent = new Map();
  objectives.forEach((obj) => {
    const parent = obj.parent_id ?? 'root';
    if (!byParent.has(parent)) byParent.set(parent, []);
    byParent.get(parent).push(obj);
  });

  const flattened = [];
  function walk(parent, level) {
    const children = byParent.get(parent) || [];
    children.sort((a, b) => a.position - b.position);
    children.forEach((child) => {
      flattened.push({ ...child, level });
      walk(child.id, level + 1);
    });
  }

  walk('root', 0);
  return flattened;
}

function cardsInCell(objectiveId, columnId) {
  return board.cards
    .filter((c) => c.objective_id === objectiveId && c.column_id === columnId)
    .sort((a, b) => a.position - b.position);
}

function renderHeader() {
  headerRow.innerHTML = '';
  const objectiveTh = document.createElement('th');
  objectiveTh.textContent = 'Objectius';
  objectiveTh.className = 'objective-col';
  headerRow.appendChild(objectiveTh);

  sortedColumns().forEach((col) => {
    const th = document.createElement('th');
    th.textContent = col.title;
    headerRow.appendChild(th);
  });
}

function renderBoard() {
  renderHeader();
  boardBody.innerHTML = '';

  const objectives = groupObjectives(board.objectives);
  objectives.forEach((objective) => {
    const tr = document.createElement('tr');
    tr.dataset.objectiveId = objective.id;

    const titleTd = document.createElement('td');
    titleTd.className = 'objective-cell';
    titleTd.style.paddingLeft = `${12 + objective.level * 20}px`;

    const title = document.createElement('div');
    title.className = 'objective-title';
    title.textContent = objective.title;

    const controls = controlsTpl.content.firstElementChild.cloneNode(true);
    controls.addEventListener('click', (event) => onObjectiveAction(event, objective));

    titleTd.appendChild(title);
    titleTd.appendChild(controls);
    tr.appendChild(titleTd);

    sortedColumns().forEach((column) => {
      const td = document.createElement('td');
      td.className = 'dropzone';
      td.dataset.objectiveId = objective.id;
      td.dataset.columnId = column.id;

      const addBtn = document.createElement('button');
      addBtn.className = 'add-card';
      addBtn.textContent = '+ Card';
      addBtn.addEventListener('click', () => openCreateCardDialog(objective.id, column.id));
      td.appendChild(addBtn);

      cardsInCell(objective.id, column.id).forEach((card) => td.appendChild(renderCard(card)));

      td.addEventListener('dragover', (e) => e.preventDefault());
      td.addEventListener('drop', (e) => onCardDrop(e, td));

      tr.appendChild(td);
    });

    boardBody.appendChild(tr);
  });
}

function renderCard(card) {
  const cardEl = document.createElement('article');
  cardEl.className = 'card';
  cardEl.draggable = true;
  cardEl.dataset.cardId = card.id;

  const body = document.createElement('div');
  body.className = 'card-body';

  const title = document.createElement('div');
  title.className = 'card-title';
  title.textContent = card.title;
  body.appendChild(title);

  if (card.code) {
    const code = document.createElement('small');
    code.className = 'card-meta';
    code.textContent = `Codi: ${card.code}`;
    body.appendChild(code);
  }

  if (card.linked_yearly_code) {
    const linked = document.createElement('small');
    linked.className = 'card-meta';
    linked.textContent = `Vinculat: ${card.linked_yearly_code}`;
    body.appendChild(linked);
  }

  const actions = document.createElement('div');
  actions.className = 'card-actions';

  const editBtn = document.createElement('button');
  editBtn.className = 'edit-card';
  editBtn.textContent = '✎';
  editBtn.title = 'Editar card';
  editBtn.addEventListener('click', () => openEditCardDialog(card));

  const delBtn = document.createElement('button');
  delBtn.className = 'delete-card';
  delBtn.textContent = '✕';
  delBtn.title = 'Eliminar card';
  delBtn.addEventListener('click', async () => {
    await api('card', 'DELETE', { id: card.id });
    await refresh();
  });

  actions.appendChild(editBtn);
  actions.appendChild(delBtn);

  cardEl.appendChild(body);
  cardEl.appendChild(actions);

  cardEl.addEventListener('dragstart', (e) => {
    e.dataTransfer.setData('application/json', JSON.stringify({ cardId: card.id }));
  });

  return cardEl;
}

function fillLinkedCodes(selected = '') {
  cardLinkedCode.innerHTML = '';
  const empty = document.createElement('option');
  empty.value = '';
  empty.textContent = 'Selecciona un codi...';
  cardLinkedCode.appendChild(empty);

  board.yearly_codes.forEach((code) => {
    const option = document.createElement('option');
    option.value = code;
    option.textContent = code;
    option.selected = code === selected;
    cardLinkedCode.appendChild(option);
  });
}

function openCreateCardDialog(objectiveId, columnId) {
  currentCardContext = {
    mode: 'create',
    objective_id: Number(objectiveId),
    column_id: Number(columnId),
    id: null,
  };

  cardDialogTitle.textContent = 'Nova card';
  cardTitle.value = '';
  cardDescription.value = '';
  cardCode.value = '';
  fillLinkedCodes('');

  const isYearly = currentCardContext.column_id === getYearlyColumnId();
  yearlyCodeRow.style.display = isYearly ? 'block' : 'none';
  linkedCodeRow.style.display = isYearly ? 'none' : 'block';

  if (!isYearly && board.yearly_codes.length === 0) {
    alert('Primer has de crear almenys una card a Yearly goals amb codi únic.');
    return;
  }

  cardDialog.showModal();
}

function openEditCardDialog(card) {
  currentCardContext = {
    mode: 'edit',
    id: Number(card.id),
    objective_id: Number(card.objective_id),
    column_id: Number(card.column_id),
  };

  cardDialogTitle.textContent = 'Editar card';
  cardTitle.value = card.title || '';
  cardDescription.value = card.description || '';
  cardCode.value = card.code || '';
  fillLinkedCodes(card.linked_yearly_code || '');

  const isYearly = currentCardContext.column_id === getYearlyColumnId();
  yearlyCodeRow.style.display = isYearly ? 'block' : 'none';
  linkedCodeRow.style.display = isYearly ? 'none' : 'block';

  cardDialog.showModal();
}

cardForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!currentCardContext) return;

  const payload = {
    title: cardTitle.value.trim(),
    description: cardDescription.value.trim(),
    code: cardCode.value.trim(),
    linked_yearly_code: cardLinkedCode.value,
  };

  if (!payload.title) {
    alert('El títol és obligatori');
    return;
  }

  const isYearly = currentCardContext.column_id === getYearlyColumnId();

  try {
    if (currentCardContext.mode === 'create') {
      await api('card', 'POST', {
        ...payload,
        objective_id: currentCardContext.objective_id,
        column_id: currentCardContext.column_id,
        code: isYearly ? payload.code : null,
        linked_yearly_code: isYearly ? null : payload.linked_yearly_code,
      });
    } else {
      await api('card', 'PUT', {
        id: currentCardContext.id,
        title: payload.title,
        description: payload.description,
        code: isYearly ? payload.code : null,
        linked_yearly_code: isYearly ? null : payload.linked_yearly_code,
      });
    }

    cardDialog.close();
    await refresh();
  } catch (err) {
    alert(err.message);
  }
});

async function onCardDrop(event, td) {
  event.preventDefault();
  const payload = JSON.parse(event.dataTransfer.getData('application/json') || '{}');
  if (!payload.cardId) return;

  const objectiveId = Number(td.dataset.objectiveId);
  const columnId = Number(td.dataset.columnId);
  const cardCount = td.querySelectorAll('.card').length;
  const newPosition = cardCount + 1;

  try {
    await api('card-move', 'PUT', {
      id: payload.cardId,
      objective_id: objectiveId,
      column_id: columnId,
      position: newPosition,
    });
    await refresh();
  } catch (err) {
    alert(err.message);
  }
}

async function onObjectiveAction(event, objective) {
  const btn = event.target.closest('button');
  if (!btn) return;

  const action = btn.dataset.action;
  if (action === 'add-child') {
    const title = prompt('Títol del subobjectiu');
    if (!title) return;
    await api('objective', 'POST', { title, parent_id: objective.id });
  } else if (action === 'move-up') {
    await api('objective-move', 'PUT', { id: objective.id, direction: 'up' });
  } else if (action === 'move-down') {
    await api('objective-move', 'PUT', { id: objective.id, direction: 'down' });
  } else if (action === 'delete') {
    if (!confirm('Eliminar objectiu i subobjectius?')) return;
    await api('objective', 'DELETE', { id: objective.id });
  }

  await refresh();
}

addRootObjectiveBtn.addEventListener('click', async () => {
  const title = prompt('Títol del nou objectiu estratègic');
  if (!title) return;
  await api('objective', 'POST', { title, parent_id: null });
  await refresh();
});

async function refresh() {
  const data = await api('board', 'GET');
  board = data.data;
  renderBoard();
}

refresh().catch((err) => {
  console.error(err);
  alert(`Error carregant el panell: ${err.message}`);
});
